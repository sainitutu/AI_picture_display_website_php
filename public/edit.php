<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: /');
    exit;
}

$db = new Database();
$image = $db->getImage($id);

if (!$image) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $db->deleteImage($id);
        header('Location: /');
        exit;
    }

    if (isset($_POST['save'])) {
        $type = $_POST['type'];
        $details = $_POST['details'];
        $isHidden = isset($_POST['is_hidden']);
        $keywords = isset($_POST['keywords']) ? explode(',', $_POST['keywords']) : [];

        $db->updateImage($id, $type, $details, $isHidden, $keywords);
        header('Location: /view.php?id=' . $id);
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
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <form method="post">
            <div class="button-group">
                <a href="/view.php?id=<?= $id ?>" class="button">取消</a>
                <button type="submit" name="delete" class="button danger" onclick="return confirm('確定要刪除這張圖片嗎？')">刪除</button>
                <button type="submit" name="save" class="button">儲存</button>
            </div>

            <div class="image-container">
                <img src="/image.php?id=<?= $id ?>" alt="AI 生成圖片" class="image-preview">
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
                <label>
                    <input type="checkbox" name="is_hidden" <?= $image['is_hidden'] ? 'checked' : '' ?>>
                    R18 內容
                </label>
            </div>
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
                    const response = await fetch(`/api/suggest.php?q=${encodeURIComponent(partial)}`);
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
                    const response = await fetch('/api/keyword.php', {
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
