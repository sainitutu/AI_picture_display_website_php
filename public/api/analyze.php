<?php
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

function sendJsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image'])) {
    sendJsonResponse(['found' => false, 'error' => '無效的請求']);
}

$file = $_FILES['image'];

try {
    error_log("Analyzing image: " . $file['name']);

    if ($file['type'] === 'image/png') {
        // Read PNG file
        $png = file_get_contents($file['tmp_name']);
        if ($png === false) {
            throw new Exception('無法讀取PNG檔案');
        }

        // Skip PNG signature
        $offset = 8;
        $length = strlen($png);
        
        while ($offset < $length) {
            // Read chunk length
            $chunkLength = unpack('N', substr($png, $offset, 4))[1];
            $offset += 4;
            
            // Read chunk type
            $chunkType = substr($png, $offset, 4);
            $offset += 4;
            
            // Check for tEXt chunk
            if ($chunkType === 'tEXt') {
                $textData = substr($png, $offset, $chunkLength);
                $parts = explode("\0", $textData, 2);
                
                if (count($parts) === 2) {
                    $keyword = $parts[0];
                    $content = $parts[1];
                    
                    error_log("Found PNG text chunk: $keyword");
                    error_log("Content: " . substr($content, 0, 100) . "...");

                    // Check for workflow field (Comfy)
                    if ($keyword === 'workflow') {
                        error_log("Found workflow field");
                        sendJsonResponse([
                            'found' => true,
                            'data' => ['workflow' => $content]
                        ]);
                    }
                    
                    // Check for parameters (SD)
                    if (strpos($content, 'Steps:') !== false && strpos($content, 'Negative prompt:') !== false) {
                        error_log("Found SD parameters");
                        sendJsonResponse([
                            'found' => true,
                            'data' => ['parameters' => $content]
                        ]);
                    }
                }
            }
            
            // Skip chunk data and CRC
            $offset += $chunkLength + 4;
        }

        // Try exiftool as fallback
        $cmd = sprintf('exiftool -j -Parameters -Comment -workflow "%s"', escapeshellarg($file['tmp_name']));
        $output = shell_exec($cmd);
        error_log("Exiftool output: " . $output);
        
        if ($output) {
            $exifData = json_decode($output, true);
            if ($exifData && isset($exifData[0])) {
                // Check for workflow (Comfy)
                if (isset($exifData[0]['workflow'])) {
                    error_log("Found workflow in exiftool data");
                    sendJsonResponse([
                        'found' => true,
                        'data' => ['workflow' => $exifData[0]['workflow']]
                    ]);
                }
                // Check for Parameters (SD)
                if (isset($exifData[0]['Parameters'])) {
                    error_log("Found Parameters in exiftool data");
                    sendJsonResponse([
                        'found' => true,
                        'data' => ['parameters' => trim($exifData[0]['Parameters'])]
                    ]);
                }
                // Check Comment for either type
                if (isset($exifData[0]['Comment'])) {
                    $comment = $exifData[0]['Comment'];
                    // Try to parse as JSON for workflow
                    try {
                        $jsonData = json_decode($comment, true);
                        if ($jsonData && isset($jsonData['workflow'])) {
                            error_log("Found workflow in Comment JSON");
                            sendJsonResponse([
                                'found' => true,
                                'data' => ['workflow' => json_encode($jsonData['workflow'], JSON_PRETTY_PRINT)]
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log("JSON parse error in Comment: " . $e->getMessage());
                    }
                    
                    // Check for SD parameters
                    if (strpos($comment, 'Steps:') !== false) {
                        error_log("Found SD parameters in Comment");
                        sendJsonResponse([
                            'found' => true,
                            'data' => ['parameters' => trim($comment)]
                        ]);
                    }
                }
            }
        }
    }

    // If we get here, no data was found
    error_log("No metadata found in image");
    sendJsonResponse([
        'found' => false,
        'data' => null
    ]);

} catch (Exception $e) {
    error_log('Image analysis error: ' . $e->getMessage());
    sendJsonResponse([
        'found' => false,
        'error' => '圖片分析失敗: ' . $e->getMessage()
    ]);
}
