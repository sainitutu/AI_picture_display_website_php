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
            console.error('Error fetching suggestions:', error);
        }
    }

    function performSearch() {
        const searchType = document.querySelector('input[name="searchType"]:checked').value;
        const includeHidden = document.querySelector('input[name="includeHidden"]').checked;
        
        const params = new URLSearchParams({
            keywords: Array.from(keywords).join(','),
            searchType: searchType,
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
