<?php
class Database {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function getDB() {
        return $this->db;
    }

    // Image operations
    public function addImage($imagePath, $type, $details, $isHidden, $keywords, $attachmentPath = null) {
        try {
            $this->db->beginTransaction();

            // Insert image
            $stmt = $this->db->prepare('INSERT INTO images (image_path, type, details, is_hidden) VALUES (?, ?, ?, ?)');
            $stmt->execute([$imagePath, $type, $details, $isHidden ? 1 : 0]);
            $imageId = $this->db->lastInsertId();

            // Add attachment if provided
            if ($attachmentPath) {
                $stmt = $this->db->prepare('INSERT INTO attachments (image_id, file_path) VALUES (?, ?)');
                $stmt->execute([$imageId, $attachmentPath]);
            }

            // Process keywords
            foreach ($keywords as $keyword) {
                // Try to insert new keyword if it doesn't exist
                $stmt = $this->db->prepare('INSERT OR IGNORE INTO keywords (keyword) VALUES (?)');
                $stmt->execute([$keyword]);

                // Get keyword ID
                $stmt = $this->db->prepare('SELECT id FROM keywords WHERE keyword = ?');
                $stmt->execute([$keyword]);
                $keywordId = $stmt->fetchColumn();

                // Link image with keyword
                $stmt = $this->db->prepare('INSERT INTO image_keywords (image_id, keyword_id) VALUES (?, ?)');
                $stmt->execute([$imageId, $keywordId]);
            }

            $this->db->commit();
            return $imageId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateImage($id, $type, $details, $isHidden, $keywords) {
        try {
            $this->db->beginTransaction();

            // Update image
            $stmt = $this->db->prepare('UPDATE images SET type = ?, details = ?, is_hidden = ? WHERE id = ?');
            $stmt->execute([$type, $details, $isHidden ? 1 : 0, $id]);

            // Remove existing keyword associations
            $stmt = $this->db->prepare('DELETE FROM image_keywords WHERE image_id = ?');
            $stmt->execute([$id]);

            // Add new keyword associations
            foreach ($keywords as $keyword) {
                $stmt = $this->db->prepare('INSERT OR IGNORE INTO keywords (keyword) VALUES (?)');
                $stmt->execute([$keyword]);

                $stmt = $this->db->prepare('SELECT id FROM keywords WHERE keyword = ?');
                $stmt->execute([$keyword]);
                $keywordId = $stmt->fetchColumn();

                $stmt = $this->db->prepare('INSERT INTO image_keywords (image_id, keyword_id) VALUES (?, ?)');
                $stmt->execute([$id, $keywordId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteImage($id) {
        try {
            $this->db->beginTransaction();

            // Delete attachments first
            $stmt = $this->db->prepare('DELETE FROM attachments WHERE image_id = ?');
            $stmt->execute([$id]);

            // Then delete the image
            $stmt = $this->db->prepare('DELETE FROM images WHERE id = ?');
            $stmt->execute([$id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addAttachment($imageId, $filePath) {
        try {
            // Check if table exists
            $tableExists = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='attachments'")->fetchColumn();
            if (!$tableExists) {
                throw new Exception('Attachments table does not exist');
            }

            $stmt = $this->db->prepare('INSERT INTO attachments (image_id, file_path) VALUES (?, ?)');
            return $stmt->execute([$imageId, $filePath]);
        } catch (Exception $e) {
            error_log('Error adding attachment: ' . $e->getMessage());
            return false;
        }
    }

    public function getAttachments($imageId) {
        try {
            error_log("Database: Getting attachments for image ID: " . $imageId);
            $stmt = $this->db->prepare('SELECT * FROM attachments WHERE image_id = ? ORDER BY created_at DESC');
            $stmt->execute([$imageId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Database: Attachments result: " . print_r($result, true));
            return $result;
        } catch (Exception $e) {
            error_log("Database: Error getting attachments: " . $e->getMessage());
            return [];
        }
    }

    public function deleteAttachment($attachmentId) {
        try {
            $stmt = $this->db->prepare('DELETE FROM attachments WHERE id = ?');
            return $stmt->execute([$attachmentId]);
        } catch (Exception $e) {
            error_log("Database: Error deleting attachment: " . $e->getMessage());
            return false;
        }
    }

    public function getAttachmentById($id) {
        try {
            error_log("Database: Getting attachment by ID: " . $id);
            $stmt = $this->db->prepare('SELECT * FROM attachments WHERE id = ?');
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Database: Attachment result: " . print_r($result, true));
            return $result;
        } catch (Exception $e) {
            error_log("Database: Error getting attachment: " . $e->getMessage());
            return null;
        }
    }

    public function getImage($id) {
        $stmt = $this->db->prepare('
            SELECT i.*, GROUP_CONCAT(k.keyword) as keywords
            FROM images i
            LEFT JOIN image_keywords ik ON i.id = ik.image_id
            LEFT JOIN keywords k ON ik.keyword_id = k.id
            WHERE i.id = ?
            GROUP BY i.id
        ');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $result['keywords'] = $result['keywords'] ? explode(',', $result['keywords']) : [];
            
            // Get attachments
            $attachStmt = $this->db->prepare('
                SELECT id, file_path, created_at
                FROM attachments
                WHERE image_id = ?
                ORDER BY created_at DESC
            ');
            $attachStmt->execute([$id]);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            $result['attachments'] = $attachments ?: [];
        }
        return $result;
    }

    public function getAllImages() {
        $stmt = $this->db->prepare('SELECT id, image_path FROM images');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchImages($keywords = [], $searchType = 'AND', $includeHidden = false, $imageType = null) {
        $query = 'SELECT DISTINCT i.* FROM images i';
        $params = [];
        $conditions = [];
        
        if (!empty($keywords)) {
            $query .= ' JOIN image_keywords ik ON i.id = ik.image_id
                       JOIN keywords k ON ik.keyword_id = k.id';
            
            if ($searchType === 'AND') {
                $placeholders = str_repeat('?,', count($keywords) - 1) . '?';
                $conditions[] = "i.id IN (
                    SELECT ik2.image_id 
                    FROM image_keywords ik2 
                    JOIN keywords k2 ON ik2.keyword_id = k2.id 
                    WHERE k2.keyword IN ($placeholders)
                    GROUP BY ik2.image_id 
                    HAVING COUNT(DISTINCT k2.keyword) = ?
                )";
                $params = array_merge($keywords, [count($keywords)]);
            } else {
                $placeholders = str_repeat('?,', count($keywords) - 1) . '?';
                $conditions[] = "k.keyword IN ($placeholders)";
                $params = $keywords;
            }
        }

        if (!$includeHidden) {
            $conditions[] = 'i.is_hidden = 0';
        }

        if ($imageType) {
            $conditions[] = 'i.type = ?';
            $params[] = $imageType;
        }

        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $query .= ' ORDER BY i.created_at DESC';
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Keyword operations
    public function getKeywordSuggestions($partial) {
        $stmt = $this->db->prepare('SELECT keyword FROM keywords WHERE keyword LIKE ? LIMIT 10');
        $stmt->execute([$partial . '%']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function addKeyword($keyword) {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO keywords (keyword) VALUES (?)');
        return $stmt->execute([$keyword]);
    }
}
