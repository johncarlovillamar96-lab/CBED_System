(() => {
    const template = document.getElementById('cbed-table-pager');
    const selector = 'table[data-table-pagination]';
    const initialized = new WeakSet();

    function paginate(table) {
        if (initialized.has(table)) return;
        initialized.add(table);

        const controls = template.content.firstElementChild.cloneNode(true);
        const parent = table.parentElement;
        const scrolls = Array.from(parent.classList).some((name) => /^overflow-(?:x-)?auto$/.test(name));
        (scrolls && parent.childElementCount === 1 ? parent : table).after(controls);

        const size = controls.querySelector('select');
        const range = controls.querySelector('[data-page-range]');
        const number = controls.querySelector('[data-page-number]');
        const previous = controls.querySelector('[data-prev-page]');
        const next = controls.querySelector('[data-next-page]');
        let page = 1;
        let rows = [];

        function render() {
            const perPage = Number(size.value);
            const pages = Math.max(1, Math.ceil(rows.length / perPage));
            page = Math.min(page, pages);
            const start = (page - 1) * perPage;
            const end = Math.min(start + perPage, rows.length);

            rows.forEach((row, index) => row.classList.toggle('cbed-row-off-page', index < start || index >= end));
            range.textContent = `Showing ${rows.length ? start + 1 : 0}–${end} of ${rows.length}`;
            number.textContent = `Page ${page} of ${pages}`;
            previous.disabled = page === 1;
            next.disabled = page === pages;
        }

        function refresh() {
            const current = Array.from(table.tBodies).flatMap((body) =>
                Array.from(body.rows).filter((row) => !row.hasAttribute('data-pagination-placeholder')
                    && !(row.cells.length === 1 && row.cells[0].colSpan > 1))
            );
            const oldRows = new Set(rows);
            if (rows.length && !current.some((row) => oldRows.has(row))) page = 1;
            rows = current;
            render();
        }

        size.addEventListener('change', () => { page = 1; render(); });
        previous.addEventListener('click', () => { page--; render(); });
        next.addEventListener('click', () => { page++; render(); });

        // Keep editable rows mounted and reveal the first invalid field before the browser focuses it.
        let invalidFieldPending = false;
        table.addEventListener('invalid', (event) => {
            if (invalidFieldPending) return;
            const index = rows.indexOf(event.target.closest('tr'));
            if (index === -1) return;
            invalidFieldPending = true;
            page = Math.floor(index / Number(size.value)) + 1;
            render();
            queueMicrotask(() => { invalidFieldPending = false; });
        }, true);

        new MutationObserver(refresh).observe(table, { childList: true, subtree: true });
        refresh();
    }

    document.querySelectorAll(selector).forEach(paginate);
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE || !node.isConnected) continue;
                if (node.matches(selector)) paginate(node);
                node.querySelectorAll(selector).forEach(paginate);
            }
        }
    }).observe(document.querySelector('main'), { childList: true, subtree: true });
})();
