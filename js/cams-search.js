function initCamsSearch() {
    document.querySelectorAll('[data-table-search]').forEach(input => {
        const target = document.querySelector(input.dataset.tableSearch);
        if (!target) return;
        const rows = Array.from(target.querySelectorAll('tbody tr')).filter(row => !row.classList.contains('search-empty-row'));
        const emptyRow = target.querySelector('.search-empty-row');
        const run = () => {
            const term = input.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(row => {
                const show = !term || row.textContent.toLowerCase().includes(term);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (emptyRow) emptyRow.style.display = visible ? 'none' : '';
        };
        input.addEventListener('input', run);
        run();
    });
}

document.addEventListener('DOMContentLoaded', initCamsSearch);
