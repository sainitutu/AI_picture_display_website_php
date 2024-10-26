<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

$db = new Database();
$keywords = isset($_GET['keywords']) && !empty($_GET['keywords']) ? explode(',', $_GET['keywords']) : [];
$includeHidden = isset($_GET['includeHidden']) && $_GET['includeHidden'] === 'true';

$images = $db->searchImages($keywords, 'OR', $includeHidden);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 圖片庫</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>AI 圖片庫</h1>
            <a href="/upload.php" class="button">新增圖片</a>
        </header>

        <div class="search-section">
            <div class="search-container">
                <div class="search-box">
                    <div class="keyword-chips"></div>
                    <input type="text" id="searchInput" placeholder="輸入關鍵詞...">
                </div>
                <div class="search-suggestions"></div>
            </div>
            
            <div class="search-options">
                <label>
                    <input type="checkbox" name="includeHidden" <?= $includeHidden ? 'checked' : '' ?>>
                    顯示隱藏圖片
                </label>
            </div>

            <button id="searchButton" class="button">搜尋</button>
        </div>

        <div class="gallery">
            <?php if (empty($images)): ?>
                <p class="no-results">沒有符合的結果</p>
            <?php else: ?>
                <?php foreach ($images as $image): ?>
                    <div class="gallery-item <?= $image['is_hidden'] ? 'hidden-image' : '' ?>">
                        <a href="/view.php?id=<?= $image['id'] ?>">
                            <img src="/thumbnail.php?id=<?= $image['id'] ?>" alt="AI 生成圖片">
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
            let keywords = new Set();

            // Load initial keywords from URL if any
            const urlParams = new URLSearchParams(window.location.search);
            const initialKeywords = urlParams.get('keywords');
            if (initialKeywords) {
                initialKeywords.split(',').forEach(keyword => addKeyword(keyword.trim()));
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
                const includeHidden = document.querySelector('input[name="includeHidden"]').checked;
                
                const params = new URLSearchParams({
                    keywords: Array.from(keywords).join(','),
                    includeHidden: includeHidden
                });

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
