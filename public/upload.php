<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save']) && isset($_FILES['image'])) {
        $file = $_FILES['image'];
        $type = $_POST['type'];
        $details = $_POST['details'];
        $isHidden = isset($_POST['is_hidden']);
        $keywords = isset($_POST['keywords']) ? explode(',', $_POST['keywords']) : [];

        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            $error = '不支援的檔案格式。僅支援 JPEG、PNG、GIF 和 WebP 圖片。';
        } else {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $filepath = UPLOAD_PATH . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $db = new Database();
                $imageId = $db->addImage($filename, $type, $details, $isHidden, $keywords);
                header('Location: /view.php?id=' . $imageId);
                exit;
            } else {
                $error = '上傳檔案失敗。';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增圖片 - AI 圖片庫</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="fixed-header">
        <div class="button-group">
            <a href="/" class="button">取消</a>
            <button type="button" class="button" onclick="document.getElementById('mainForm').submit();">儲存</button>
        </div>
    </div>

    <div class="container">
        <form id="mainForm" method="post" enctype="multipart/form-data">
            <?php if (isset($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="image">圖片：</label>
                <input type="file" name="image" id="image" accept="image/*" required>
                <div id="imagePreview"></div>
            </div>

            <div class="form-group">
                <label for="type">類型：</label>
                <select name="type" id="type" required>
                    <option value="SD">SD</option>
                    <option value="Comfy">Comfy</option>
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
                <input type="hidden" name="keywords" id="keywordsInput" value="">
                <div class="keyword-buttons">
                    <button type="button" id="addKeywordBtn" class="button">新增關鍵詞</button>
                    <button type="button" id="analyzeKeywordsBtn" class="button">分析關鍵詞</button>
                </div>
            </div>

            <div class="form-group">
                <label for="details">詳細資訊：</label>
                <textarea name="details" id="details"></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_hidden">
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

    <div id="analyzePromptModal" class="modal">
        <div class="modal-content">
            <h2>分析關鍵詞</h2>
            <div class="form-group">
                <label for="promptInput">請輸入提詞(正向提詞)</label>
                <textarea id="promptInput"></textarea>
            </div>
            <div class="button-group">
                <button id="cancelAnalyzeBtn" class="button">取消</button>
                <button id="analyzePromptBtn" class="button">分析</button>
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
            const analyzeKeywordsBtn = document.getElementById('analyzeKeywordsBtn');
            const keywordModal = document.getElementById('keywordModal');
            const analyzePromptModal = document.getElementById('analyzePromptModal');
            const newKeywordInput = document.getElementById('newKeywordInput');
            const promptInput = document.getElementById('promptInput');
            const saveKeywordBtn = document.getElementById('saveKeywordBtn');
            const cancelKeywordBtn = document.getElementById('cancelKeywordBtn');
            const analyzePromptBtn = document.getElementById('analyzePromptBtn');
            const cancelAnalyzeBtn = document.getElementById('cancelAnalyzeBtn');
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');
            const typeSelect = document.getElementById('type');
            const detailsTextarea = document.getElementById('details');
            let keywords = new Set();

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

            async function analyzePrompt(prompt) {
                try {
                    const response = await fetch('/api/analyze_keywords.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ prompt }),
                    });
                    
                    if (!response.ok) {
                        throw new Error('分析失敗');
                    }

                    const data = await response.json();
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    return data.keywords;
                } catch (error) {
                    console.error('分析關鍵詞時發生錯誤：', error);
                    alert('分析關鍵詞失敗：' + error.message);
                    return null;
                }
            }

            async function analyzeImage(file) {
                const formData = new FormData();
                formData.append('image', file);

                try {
                    console.log('Sending image for analysis:', file.name);
                    const response = await fetch('/api/analyze.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const text = await response.text();
                    console.log('Raw response:', text);

                    if (!text.trim()) {
                        console.error('Empty response received');
                        return null;
                    }

                    try {
                        const result = JSON.parse(text);
                        console.log('Parsed result:', result);

                        if (result.error) {
                            console.error('Analysis error:', result.error);
                            return null;
                        }

                        if (!result.found) {
                            console.log('No image info found');
                            return null;
                        }

                        return result.data;
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Raw response:', text);
                        return null;
                    }
                } catch (error) {
                    console.error('Image analysis failed:', error);
                    return null;
                }
            }

            // Image preview and analysis
            imageInput.addEventListener('change', async function() {
                const file = this.files[0];
                if (file) {
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `<img src="${e.target.result}" class="image-preview">`;
                    };
                    reader.readAsDataURL(file);

                    try {
                        // Analyze image
                        const metadata = await analyzeImage(file);
                        console.log('Analysis result:', metadata);
                        if (metadata) {
                            if (metadata.parameters) {
                                typeSelect.value = 'SD';
                                detailsTextarea.value = metadata.parameters;
                            } else if (metadata.workflow) {
                                typeSelect.value = 'Comfy';
                                detailsTextarea.value = metadata.workflow;
                            }
                        }
                    } catch (error) {
                        console.error('處理圖片時發生錯誤：', error);
                    }
                }
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

            analyzeKeywordsBtn.addEventListener('click', () => {
                promptInput.value = '';
                analyzePromptModal.style.display = 'flex';
            });

            cancelKeywordBtn.addEventListener('click', () => {
                keywordModal.style.display = 'none';
                newKeywordInput.value = '';
            });

            cancelAnalyzeBtn.addEventListener('click', () => {
                analyzePromptModal.style.display = 'none';
                promptInput.value = '';
            });

            saveKeywordBtn.addEventListener('click', () => {
                const keyword = newKeywordInput.value.trim();
                if (keyword) {
                    addNewKeyword(keyword);
                }
            });

            analyzePromptBtn.addEventListener('click', async () => {
                const prompt = promptInput.value.trim();
                if (!prompt) {
                    alert('請輸入提詞');
                    return;
                }

                const matchedKeywords = await analyzePrompt(prompt);
                if (matchedKeywords && matchedKeywords.length > 0) {
                    matchedKeywords.forEach(keyword => addKeyword(keyword));
                    analyzePromptModal.style.display = 'none';
                    promptInput.value = '';
                } else {
                    alert('未找到匹配的關鍵詞');
                }
            });

            // Close suggestions when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) {
                    searchSuggestions.style.display = 'none';
                }
            });

            // Close modals when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target === keywordModal) {
                    keywordModal.style.display = 'none';
                    newKeywordInput.value = '';
                }
                if (e.target === analyzePromptModal) {
                    analyzePromptModal.style.display = 'none';
                    promptInput.value = '';
                }
            });
        });
    </script>
</body>
</html>
