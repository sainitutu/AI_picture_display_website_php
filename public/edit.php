<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

// Increase upload limits
ini_set('upload_max_filesize', '64M');
ini_set('post_max_size', '64M');
ini_set('memory_limit', '512M');
ini_set('max_file_uploads', '20'); // Allow up to 20 files to be uploaded simultaneously
error_log("Upload limits: " . 
    "upload_max_filesize=" . ini_get('upload_max_filesize') . "/" .
    "post_max_size=" . ini_get('post_max_size') . "/" .
    "memory_limit=" . ini_get('memory_limit') . "/" .
    "max_file_uploads=" . ini_get('max_file_uploads')
);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: ./');
    exit;
}

$db = new Database();
$image = $db->getImage($id);

if (!$image) {
        header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $db->deleteImage($id);
        header('Location: /');
        exit;
    }

    if (isset($_POST['delete_attachment'])) {
        $attachmentId = (int)$_POST['attachment_id'];
        $db->deleteAttachment($attachmentId);
        header('Location: edit.php?id=' . $id);
        exit;
    }

    if (isset($_POST['save'])) {
        $type = $_POST['type'];
        $details = $_POST['details'];
        $isHidden = isset($_POST['is_hidden']);
        $keywords = isset($_POST['keywords']) ? explode(',', $_POST['keywords']) : [];

        // Handle attachment uploads
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $uploadDir = __DIR__ . '/../assets/attachments/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            error_log("Upload: Received files: " . print_r($_FILES['attachments'], true));

            foreach ($_FILES['attachments']['name'] as $i => $name) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK && $_FILES['attachments']['size'][$i] > 0) {
                    $fileExtension = pathinfo($name, PATHINFO_EXTENSION);
                    $newFileName = uniqid() . '.' . $fileExtension;
                    $uploadPath = $uploadDir . $newFileName;

                    error_log("Upload: Processing file {$i}: {$name}");
                    error_log("Upload: File info: " . print_r([
                        'name' => $name,
                        'type' => $_FILES['attachments']['type'][$i],
                        'size' => $_FILES['attachments']['size'][$i],
                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i]
                    ], true));

                    if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $uploadPath)) {
                        error_log("Upload: File saved to: " . $uploadPath);
                        error_log("Upload: File size: " . filesize($uploadPath));
                        
                        // Store relative path in database
                        $relativePath = 'attachments/' . $newFileName;
                        error_log("Upload: Storing path in DB: " . $relativePath);
                        $db->addAttachment($id, $relativePath);
                    } else {
                        error_log("Upload: Failed to move uploaded file {$name}");
                    }
                } else if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    error_log("Upload: Error for file {$i}: " . $_FILES['attachments']['error'][$i]);
                }
            }
        } else {
            error_log("Upload: No files received or invalid format");
            error_log("Upload: FILES array: " . print_r($_FILES, true));
        }

        $db->updateImage($id, $type, $details, $isHidden, $keywords);
        header('Location: view.php?id=' . $id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯圖片 - AI 圖片庫</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="fixed-header">
        <div class="button-group">
            <a href="view.php?id=<?= $id ?>" class="button">取消</a>
            <button type="button" class="button danger" onclick="if(confirm('確定要刪除這張圖片嗎？')) document.getElementById('deleteForm').submit();">刪除</button>
            <button type="button" class="button" onclick="submitForm()">儲存</button>
        </div>
    </div>

    <div class="container">
        <form id="deleteForm" method="post" style="display: none;">
            <input type="hidden" name="delete" value="1">
        </form>

        <form id="mainForm" method="post" enctype="multipart/form-data">
            <div class="image-container">
                <img src="image.php?id=<?= $id ?>" alt="AI 生成圖片" class="image-preview">
            </div>

            <div class="form-group">
                <label for="type">類型：</label>
                <select name="type" id="type" required>
                    <option value="SD" <?= $image['type'] === 'SD' ? 'selected' : '' ?>>SD</option>
                    <option value="Comfy" <?= $image['type'] === 'Comfy' ? 'selected' : '' ?>>Comfy</option>
                </select>
            </div>

            <div class="form-group">
                <label>關鍵詞：</label>
                <div class="search-container">
                    <div class="search-box">
                        <div class="keyword-chips"></div>
                        <input type="text" id="keywordInput" placeholder="輸入關鍵詞...">
                    </div>
                    <div class="search-suggestions"></div>
                </div>
                <input type="hidden" name="keywords" id="keywordsInput" value="<?= htmlspecialchars(implode(',', $image['keywords'])) ?>">
                <button type="button" id="addKeywordBtn" class="button">新增關鍵詞</button>
            </div>

            <div class="form-group">
                <label for="details">詳細資訊：</label>
                <textarea name="details" id="details"><?= htmlspecialchars($image['details']) ?></textarea>
            </div>

            <div class="form-group">
                <label>副件：</label>
                <?php if (!empty($image['attachments'])): ?>
                    <div class="current-attachments">
                        <div class="attachment-list">
                            <?php foreach ($image['attachments'] as $attachment): ?>
                                <div class="attachment-item">
                                    <span class="attachment-name"><?= basename($attachment['file_path']) ?></span>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="attachment_id" value="<?= $attachment['id'] ?>">
                                        <button type="submit" name="delete_attachment" class="button danger small" onclick="return confirm('確定要刪除這個副件嗎？')">刪除</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" name="attachments[]" multiple accept=".json,.txt,.png,.jpg,.jpeg,.webp">
                <div class="help-text">支援的檔案類型：JSON、TXT、PNG、JPG、WEBP（可同時選擇多個檔案）</div>
            </div>

            <script>
                document.querySelector('input[type="file"]').addEventListener('change', function(e) {
                    const files = e.target.files;
                    if (files.length > 0) {
                        const fileList = Array.from(files).map(f => f.name).join(', ');
                        const helpText = this.nextElementSibling;
                        helpText.innerHTML = `已選擇 ${files.length} 個檔案：${fileList}<br>支援的檔案類型：JSON、TXT、PNG、JPG、WEBP（可同時選擇多個檔案）`;
                    }
                });
            </script>

            <style>
                .attachment-list {
                    margin: 10px 0;
                }
                .attachment-item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 5px;
                    margin: 5px 0;
                    background: #f5f5f5;
                    border-radius: 4px;
                }
                .attachment-name {
                    margin-right: 10px;
                }
                .button.small {
                    padding: 2px 8px;
                    font-size: 0.9em;
                }
            </style>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_hidden" <?= $image['is_hidden'] ? 'checked' : '' ?>>
                    R18 內容
                </label>
            </div>

            <input type="hidden" name="save" value="1">
        </form>
    </div>

    <div id="keywordModal" class="modal">
        <div class="modal-content">
            <h2>新增關鍵詞</h2>
            <input type="text" id="newKeywordInput" placeholder="輸入新關鍵詞">
            <div class="button-group">
                <button id="cancelKeywordBtn" class="button">取消</button>
                <button id="saveKeywordBtn" class="button">儲存</button>
            </div>
        </div>
    </div>

    <script>
        function submitForm() {
            const form = document.getElementById('mainForm');
            const fileInput = form.querySelector('input[type="file"]');
            const files = fileInput.files;
            
            // If files are selected, ensure they are properly attached
            if (files.length > 0) {
                const formData = new FormData(form);
                // Verify files are in FormData
                const attachments = formData.getAll('attachments[]');
                if (attachments.length === 0) {
                    alert('檔案上傳準備中，請稍候再試');
                    return;
                }
            }
            
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const keywordInput = document.getElementById('keywordInput');
            const keywordChips = document.querySelector('.keyword-chips');
            const keywordsInput = document.getElementById('keywordsInput');
            const searchSuggestions = document.querySelector('.search-suggestions');
            const addKeywordBtn = document.getElementById('addKeywordBtn');
            const keywordModal = document.getElementById('keywordModal');
            const newKeywordInput = document.getElementById('newKeywordInput');
            const saveKeywordBtn = document.getElementById('saveKeywordBtn');
            const cancelKeywordBtn = document.getElementById('cancelKeywordBtn');
            let keywords = new Set(<?= json_encode($image['keywords']) ?>);

            function updateKeywordsInput() {
                keywordsInput.value = Array.from(keywords).join(',');
            }

            function addKeyword(keyword) {
                if (keyword && !keywords.has(keyword)) {
                    keywords.add(keyword);
                    const chip = document.createElement('div');
                    chip.className = 'keyword-chip';
                    chip.innerHTML = `
                        ${keyword}
                        <span class="remove" data-keyword="${keyword}">×</span>
                    `;
                    keywordChips.appendChild(chip);
                    updateKeywordsInput();
                }
                keywordInput.value = '';
                updateSearchSuggestions('');
            }

            function removeKeyword(keyword) {
                keywords.delete(keyword);
                const chips = keywordChips.querySelectorAll('.keyword-chip');
                chips.forEach(chip => {
                    if (chip.querySelector('.remove').dataset.keyword === keyword) {
                        chip.remove();
                    }
                });
                updateKeywordsInput();
            }

            async function updateSearchSuggestions(partial) {
                if (!partial) {
                    searchSuggestions.style.display = 'none';
                    return;
                }

                try {
                    const response = await fetch(`api/suggest.php?q=${encodeURIComponent(partial)}`);
                    const suggestions = await response.json();
                    
                    if (suggestions.length > 0) {
                        searchSuggestions.innerHTML = suggestions
                            .filter(s => !keywords.has(s))
                            .map(s => `<div class="suggestion-item">${s}</div>`)
                            .join('');
                        searchSuggestions.style.display = 'block';
                    } else {
                        searchSuggestions.style.display = 'none';
                    }
                } catch (error) {
                    console.error('獲取建議時發生錯誤：', error);
                }
            }

            async function addNewKeyword(keyword) {
                try {
                    const response = await fetch('api/keyword.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ keyword }),
                    });
                    
                    if (response.ok) {
                        addKeyword(keyword);
                        keywordModal.style.display = 'none';
                        newKeywordInput.value = '';
                    } else {
                        const data = await response.json();
                        alert(data.error || '新增關鍵詞失敗');
                    }
                } catch (error) {
                    console.error('新增關鍵詞時發生錯誤：', error);
                    alert('新增關鍵詞失敗');
                }
            }

            // Initialize keyword chips
            keywords.forEach(keyword => {
                const chip = document.createElement('div');
                chip.className = 'keyword-chip';
                chip.innerHTML = `
                    ${keyword}
                    <span class="remove" data-keyword="${keyword}">×</span>
                `;
                keywordChips.appendChild(chip);
            });

            // Event Listeners
            keywordInput.addEventListener('input', (e) => {
                updateSearchSuggestions(e.target.value.trim());
            });

            keywordInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && e.target.value.trim()) {
                    addKeyword(e.target.value.trim());
                }
            });

            searchSuggestions.addEventListener('click', (e) => {
                if (e.target.classList.contains('suggestion-item')) {
                    addKeyword(e.target.textContent);
                }
            });

            keywordChips.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove')) {
                    removeKeyword(e.target.dataset.keyword);
                }
            });

            addKeywordBtn.addEventListener('click', () => {
                keywordModal.style.display = 'flex';
            });

            cancelKeywordBtn.addEventListener('click', () => {
                keywordModal.style.display = 'none';
                newKeywordInput.value = '';
            });

            saveKeywordBtn.addEventListener('click', () => {
                const keyword = newKeywordInput.value.trim();
                if (keyword) {
                    addNewKeyword(keyword);
                }
            });

            // Close suggestions when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) {
                    searchSuggestions.style.display = 'none';
                }
            });

            // Close modal when clicking outside
            keywordModal.addEventListener('click', (e) => {
                if (e.target === keywordModal) {
                    keywordModal.style.display = 'none';
                    newKeywordInput.value = '';
                }
            });
        });
    </script>
</body>
</html>
