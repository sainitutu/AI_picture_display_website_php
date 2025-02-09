<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

class ImageCleanup {
    private $db;
    private $dryRun = false;
    private $verbose = false;
    private $errors = [];

    public function __construct($dryRun = false, $verbose = false) {
        $this->db = new Database();
        $this->dryRun = $dryRun;
        $this->verbose = $verbose;
    }

    private function log($message) {
        if ($this->verbose) {
            error_log($message);
        }
    }

    private function addError($message) {
        $this->errors[] = $message;
        $this->log("Error: " . $message);
    }

    private function getAllDatabaseImages() {
        try {
            $images = $this->db->getAllImages();
            return array_column($images, 'image_path');
        } catch (Exception $e) {
            $this->addError("資料庫查詢失敗: " . $e->getMessage());
            return false;
        }
    }

    private function getAllDatabaseAttachments() {
        try {
            $stmt = $this->db->getDB()->prepare('SELECT file_path FROM attachments');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->addError("取得附件清單失敗: " . $e->getMessage());
            return false;
        }
    }

    private function scanDirectory($dir) {
        if (!file_exists($dir)) {
            $this->addError("目錄不存在: " . $dir);
            return [];
        }

        if (!is_readable($dir)) {
            $this->addError("目錄無法讀取: " . $dir);
            return [];
        }

        try {
            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    // Get path relative to the directory
                    $relativePath = str_replace($dir, '', $file->getPathname());
                    $relativePath = ltrim($relativePath, '/\\');
                    $files[] = $relativePath;
                }
            }

            return $files;
        } catch (Exception $e) {
            $this->addError("掃描目錄失敗 {$dir}: " . $e->getMessage());
            return [];
        }
    }

    private function deleteFile($path) {
        if ($this->dryRun) {
            $this->log("Would delete: $path");
            return true;
        }

        if (!file_exists($path)) {
            $this->log("File already deleted: $path");
            return true;
        }

        if (!is_writable($path)) {
            $this->addError("檔案無法刪除 (權限不足): $path");
            return false;
        }

        try {
            if (unlink($path)) {
                $this->log("Deleted: $path");
                return true;
            } else {
                $this->addError("刪除檔案失敗: $path");
                return false;
            }
        } catch (Exception $e) {
            $this->addError("刪除檔案時發生錯誤 {$path}: " . $e->getMessage());
            return false;
        }
    }

    private function cleanupEmptyDirectories($dir) {
        if (!file_exists($dir)) {
            return;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $path) {
                if ($path->isDir()) {
                    $dirPath = $path->getPathname();
                    if (count(scandir($dirPath)) == 2) { // Only . and ..
                        if ($this->dryRun) {
                            $this->log("Would remove empty directory: $dirPath");
                        } else {
                            if (is_writable($dirPath)) {
                                if (rmdir($dirPath)) {
                                    $this->log("Removed empty directory: $dirPath");
                                } else {
                                    $this->addError("無法刪除空目錄: $dirPath");
                                }
                            } else {
                                $this->addError("目錄無法刪除 (權限不足): $dirPath");
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->addError("清理空目錄時發生錯誤: " . $e->getMessage());
        }
    }

    public function cleanup() {
        try {
            // Reset errors array
            $this->errors = [];

            // Get all image paths from database
            $dbImages = $this->getAllDatabaseImages();
            if ($dbImages === false) {
                throw new Exception("無法取得資料庫圖片清單: " . implode(", ", $this->errors));
            }
            $this->log("Found " . count($dbImages) . " images in database");

            // Scan directories
            $uploadFiles = $this->scanDirectory(UPLOAD_PATH);
            $this->log("Found " . count($uploadFiles) . " files in upload directory");

            $thumbnailFiles = $this->scanDirectory(THUMBNAIL_PATH);
            $this->log("Found " . count($thumbnailFiles) . " files in thumbnail directory");

            $attachmentFiles = $this->scanDirectory(__DIR__ . '/../assets/attachments/');
            $this->log("Found " . count($attachmentFiles) . " files in attachments directory");

            // Get all valid attachment paths from database
            $dbAttachments = $this->getAllDatabaseAttachments();
            if ($dbAttachments === false) {
                throw new Exception("無法取得資料庫附件清單");
            }
            $this->log("Found " . count($dbAttachments) . " attachments in database");

            // Find and delete orphaned files
            $orphanedUploads = array_diff($uploadFiles, $dbImages);
            $this->log("Found " . count($orphanedUploads) . " orphaned files in upload directory");

            $deletedUploads = 0;
            foreach ($orphanedUploads as $file) {
                if ($this->deleteFile(UPLOAD_PATH . $file)) {
                    $deletedUploads++;
                }
            }

            $orphanedThumbnails = array_diff($thumbnailFiles, $dbImages);
            $this->log("Found " . count($orphanedThumbnails) . " orphaned files in thumbnail directory");

            $deletedThumbnails = 0;
            foreach ($orphanedThumbnails as $file) {
                if ($this->deleteFile(THUMBNAIL_PATH . $file)) {
                    $deletedThumbnails++;
                }
            }

            // Find and delete orphaned attachment files
            $orphanedAttachments = array_diff($attachmentFiles, $dbAttachments);
            $this->log("Found " . count($orphanedAttachments) . " orphaned files in attachments directory");

            $deletedAttachments = 0;
            foreach ($orphanedAttachments as $file) {
                if ($this->deleteFile(__DIR__ . '/../assets/attachments/' . $file)) {
                    $deletedAttachments++;
                }
            }

            // Cleanup empty directories
            $this->cleanupEmptyDirectories(UPLOAD_PATH);
            $this->cleanupEmptyDirectories(THUMBNAIL_PATH);
            $this->cleanupEmptyDirectories(__DIR__ . '/../assets/attachments/');

            // Prepare results
            $results = [
                'total_db_images' => count($dbImages),
                'total_upload_files' => count($uploadFiles),
                'total_thumbnail_files' => count($thumbnailFiles),
                'deleted_uploads' => $deletedUploads,
                'deleted_thumbnails' => $deletedThumbnails,
                'deleted_attachments' => $deletedAttachments
            ];

            if (!empty($this->errors)) {
                $results['errors'] = $this->errors;
                $this->log("Cleanup completed with errors: " . implode(", ", $this->errors));
            } else {
                $this->log("Cleanup completed successfully");
            }

            return $results;

        } catch (Exception $e) {
            $this->addError($e->getMessage());
            error_log("Cleanup failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw new Exception("清理過程失敗: " . $e->getMessage());
        }
    }
}
