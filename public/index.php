<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$keywords = isset($_GET['keywords']) && !empty($_GET['keywords']) ? explode(',', $_GET['keywords']) : [];
$imageVisibility = isset($_GET['visibility']) ? $_GET['visibility'] : 'hide_restricted';
$imageType = isset($_GET['type']) ? $_GET['type'] : null;

// Convert visibility option to includeHidden parameter
$includeHidden = $imageVisibility === 'show_restricted';
// For 'only_restricted', we'll modify the query in Database class
$onlyRestricted = $imageVisibility === 'only_restricted';

$images = $db->searchImages($keywords, 'OR', $includeHidden || $onlyRestricted, $imageType);

// Filter for only restricted images if that option is selected
if ($onlyRestricted) {
    $images = array_filter($images, function($image) {
        return $image['is_hidden'] == 1;
    });
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 圖片庫</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .cleanup-result {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 4px;
            display: none;
            z-index: 2000;
            max-width: 300px;
            word-break: break-word;
        }

        .cleanup-result.success {
            background-color: #2ecc71;
            color: white;
        }

        .cleanup-result.error {
            background-color: #e74c3c;
            color: white;
        }

        .loading {
            display: none;
            margin-left: 10px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>AI 圖片庫</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button id="cleanupBtn" class="button">整理資料</button>
                <span id="cleanupLoading" class="loading">⟳</span>
                <a href="/upload.php" class="button">新增圖片</a>
            </div>
        </header>

        <div id="cleanupResult" class="cleanup-result"></div>

        <div class="search-section">
            <div class="search-container">
                <div class="search-box">
                    <div class="keyword-chips"></div>
                    <input type="text" id="searchInput" placeholder="輸入關鍵詞...">
                </div>
                <div class="search-suggestions"></div>
            </div>
            
            <div class="search-options">
                <div class="search-options-right">
                    <div class="visibility-filter">
                        <label>限制圖片：</label>
                        <select name="visibility" id="visibilityFilter">
                            <option value="hide_restricted" <?= $imageVisibility === 'hide_restricted' ? 'selected' : '' ?>>隱藏限制圖片</option>
                            <option value="show_restricted" <?= $imageVisibility === 'show_restricted' ? 'selected' : '' ?>>顯示限制圖片</option>
                            <option value="only_restricted" <?= $imageVisibility === 'only_restricted' ? 'selected' : '' ?>>只顯示限制圖片</option>
                        </select>
                    </div>
                    <div class="type-filter">
                        <label>類型：</label>
                        <select name="type" id="typeFilter">
                            <option value="">全部</option>
                            <option value="SD" <?= $imageType === 'SD' ? 'selected' : '' ?>>SD</option>
                            <option value="Comfy" <?= $imageType === 'Comfy' ? 'selected' : '' ?>>Comfy</option>
                        </select>
                    </div>
                    <button id="searchButton" class="button">搜尋</button>
                </div>
            </div>
        </div>

        <div class="gallery">
            <?php if (empty($images)): ?>
                <p class="no-results">沒有符合的結果</p>
            <?php else: ?>
                <?php foreach ($images as $image): ?>
                    <div class="gallery-item <?= $image['is_hidden'] ? 'hidden-image' : '' ?>">
                        <a href="/view.php?id=<?= $image['id'] ?>">
                            <img src="/thumbnail.php?id=<?= $image['id'] ?>" alt="AI 生成圖片">
                            <span class="image-type <?= strtolower($image['type']) ?>"><?= $image['type'] ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const keywordChips = document.querySelector('.keyword-chips');
            const searchSuggestions = document.querySelector('.search-suggestions');
            const searchButton = document.getElementById('searchButton');
            const cleanupBtn = document.getElementById('cleanupBtn');
            const cleanupLoading = document.getElementById('cleanupLoading');
            const cleanupResult = document.getElementById('cleanupResult');
            let keywords = new Set();

            // Load initial keywords from URL if any
            const urlParams = new URLSearchParams(window.location.search);
            const initialKeywords = urlParams.get('keywords');
            if (initialKeywords) {
                initialKeywords.split(',').forEach(keyword => addKeyword(keyword.trim()));
            }

            function showCleanupResult(message, isSuccess) {
                cleanupResult.textContent = message;
                cleanupResult.className = 'cleanup-result ' + (isSuccess ? 'success' : 'error');
                cleanupResult.style.display = 'block';
                
                setTimeout(() => {
                    cleanupResult.style.display = 'none';
                }, 5000);
            }

            cleanupBtn.addEventListener('click', async () => {
                if (!confirm('確定要整理資料嗎？這將刪除未使用的圖片檔案。')) {
                    return;
                }

                cleanupBtn.disabled = true;
                cleanupLoading.style.display = 'inline-block';

                try {
                    const response = await fetch('/api/cleanup.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        showCleanupResult(data.message, true);
                    } else {
                        showCleanupResult(data.error || '整理資料失敗', false);
                    }
                } catch (error) {
                    showCleanupResult('整理資料時發生錯誤', false);
                } finally {
                    cleanupBtn.disabled = false;
                    cleanupLoading.style.display = 'none';
                }
            });

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
                }
                searchInput.value = '';
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

            function performSearch() {
                const visibility = document.getElementById('visibilityFilter').value;
                const selectedType = document.getElementById('typeFilter').value;
                
                const params = new URLSearchParams({
                    keywords: Array.from(keywords).join(','),
                    visibility: visibility
                });

                if (selectedType) {
                    params.append('type', selectedType);
                }

                window.location.href = `/?${params.toString()}`;
            }

            // Event Listeners
            searchInput.addEventListener('input', (e) => {
                updateSearchSuggestions(e.target.value.trim());
            });

            searchInput.addEventListener('keypress', (e) => {
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

            searchButton.addEventListener('click', performSearch);

            // Close suggestions when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) {
                    searchSuggestions.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
