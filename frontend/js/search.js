// search.js ,  autocomplete for pages that don't load main.js
// Reads hampers from localStorage snapshot written by main.js,
// or falls back to a static copy for reliability.

// Static hampers mirror ,  keep in sync with main.js hampers array
const searchHampers = [
    { name: 'hamper1', badge: '', desc: 'i dont know', img: '../img/hampers/hamper1.jpeg', price: 'MVR 500' },
    { name: 'hamper2', badge: 'Signature', desc: 'i dont know', img: '../img/hampers/hamper2.jpeg', price: 'MVR 800' },
    { name: 'hamper3', badge: null, desc: 'i dont know', img: '../img/hampers/hamper3.jpeg', price: 'MVR 600' },
    { name: 'hamper4', badge: 'Corporate', desc: 'i dont know', img: '../img/hampers/hamper4.jpeg', price: 'MVR 1,200' },
    { name: 'hamper5', badge: null, desc: 'i dont know', img: '../img/hampers/hamper5.jpeg', price: 'MVR 700' },
    { name: 'hamper6', badge: 'Custom', desc: 'i dont know', img: '../img/hampers/hamper6.jpeg', price: 'MVR 950' },
];

(function initSearch() {
    const input = document.getElementById('hamper-search');
    if (!input) return;

    let dropdown = document.getElementById('search-dropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'search-dropdown';
        dropdown.className = 'search-dropdown';
        document.body.appendChild(dropdown);
    }

    function getMatches(query) {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);
        return searchHampers.filter(h => {
            const searchable = [h.name, h.badge || '', h.desc].join(' ').toLowerCase();
            return words.every(w => searchable.includes(w));
        });
    }

    function showDropdown(query) {
        if (!query) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); return; }
        const matches = getMatches(query);
        if (!matches.length) {
            dropdown.innerHTML = `<div class="search-dd-item search-dd-none">No results for "${query}"</div>`;
            dropdown.classList.add('open');
            return;
        }
        dropdown.innerHTML = matches.map(h => `
            <div class="search-dd-item" onclick="goSearch('${encodeURIComponent(h.name)}')">
                <div>
                    <div class="search-dd-name">${h.name}</div>
                    ${h.badge ? `<div class="search-dd-badge">${h.badge}</div>` : ''}
                </div>
            </div>
        `).join('');
        dropdown.innerHTML += `<div class="search-dd-item search-dd-all" onclick="goSearch('${encodeURIComponent(query)}')">
            <i class="fas fa-search"></i> See all results for "<strong>${query}</strong>"
        </div>`;
        dropdown.classList.add('open');
    }

    input.addEventListener('input', () => showDropdown(input.value.trim()));

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            const q = input.value.trim();
            if (q) { dropdown.innerHTML = ''; dropdown.classList.remove('open'); goSearch(encodeURIComponent(q)); }
        }
        if (e.key === 'Escape') { dropdown.innerHTML = ''; dropdown.classList.remove('open'); }
    });

    document.addEventListener('click', e => {
        if (!input.closest('.header-search-wrap').contains(e.target)) {
            dropdown.innerHTML = ''; dropdown.classList.remove('open');
        }
    });
})();

function goSearch(encodedQuery) {
    window.location.href = '../search.html?q=' + encodedQuery;
}
