(() => {
    const viewport = matchMedia('(max-width: 1023px)');
    const excluded = '#animal-monitoring-print, .pdf-report-table, [data-mobile-table-disabled]';
    const searchSelector = 'input[type="search"], input[name="search"], input[name="q"], input[name$="_search"]';
    let filterId = 0;

    function columnLabels(table) {
        const grid = [];
        for (const [index, row] of [...table.tHead.rows].entries()) {
            grid[index] ??= [];
            let column = 0;
            for (const cell of row.cells) {
                while (grid[index][column] !== undefined) column++;
                const label = cell.textContent.trim().replace(/\s+/g, ' ');
                for (let y = index; y < index + cell.rowSpan; y++) {
                    grid[y] ??= [];
                    for (let x = column; x < column + cell.colSpan; x++) grid[y][x] = label;
                }
                column += cell.colSpan;
            }
        }
        const bottom = grid.at(-1) ?? [];
        return bottom.map((label, column) => bottom.indexOf(label) === bottom.lastIndexOf(label)
            ? label : [...new Set(grid.map(row => row[column]).filter(Boolean))].join(' / '));
    }

    function cardTable(table) {
        if (!table.tHead || !table.tBodies.length || table.closest(excluded)) return;
        const labels = columnLabels(table);
        if (labels.length < 5 && !table.className.includes('min-w-') && !table.closest('#monitoring-form')) return;
        const editor = Boolean(table.closest('.cbed-mobile-editor, form:not([method="GET" i]):not([method="dialog"])'));
        table.classList.add('cbed-mobile-table');
        table.classList.toggle('cbed-mobile-table-editor', editor);
        for (const body of table.tBodies) {
            const spans = [];
            for (const [rowIndex, row] of [...body.rows].entries()) {
                const cells = [...row.cells].filter(cell => !cell.classList.contains('cbed-record-toggle-cell'));
                if (!cells.length) continue;
                let column = 0;
                for (const cell of cells) {
                    while ((spans[column] ?? 0) > rowIndex) column++;
                    if (cell.rowSpan > 1) {
                        for (let index = column; index < column + cell.colSpan; index++) spans[index] = rowIndex + cell.rowSpan;
                    }
                    cell.dataset.mobileLabel = labels[column] || '';
                    column += cell.colSpan;
                }
                if (cells.length === 1) {
                    row.classList.add('cbed-record-full');
                    continue;
                }
                row.classList.add('cbed-record-card');
                const summary = cells.find(cell => cell.scope === 'row')
                    ?? cells.find(cell => cell.dataset.mobileLabel && !/^(no\.?|#|number|actions?)$/i.test(cell.dataset.mobileLabel))
                    ?? cells[0];
                for (const cell of cells) cell.classList.toggle('cbed-record-summary', cell === summary);
                if (editor || row.querySelector('.cbed-record-toggle-cell')) continue;
                const cell = document.createElement('td');
                cell.className = 'cbed-record-toggle-cell';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'cbed-record-toggle';
                const expanded = row.classList.contains('cbed-record-open');
                button.textContent = expanded ? 'Hide details' : 'Details';
                button.setAttribute('aria-expanded', String(expanded));
                button.addEventListener('click', () => {
                    const open = row.classList.toggle('cbed-record-open');
                    button.setAttribute('aria-expanded', String(open));
                    button.textContent = open ? 'Hide details' : 'Details';
                });
                cell.append(button);
                row.prepend(cell);
            }
        }
    }

    function filters(container) {
        if (!container || container.dataset.mobileFiltersReady || container.closest('dialog, .cbed-mobile-editor')) return;
        const controls = [...container.querySelectorAll('select, input[type="date"], input[type="month"]')];
        if (controls.length < 2) return;
        const fields = new Set();
        for (const control of controls) {
            let field = control.parentElement;
            while (field.parentElement !== container && !field.matches('label') && field.parentElement.querySelectorAll('select, input').length === 1) {
                field = field.parentElement;
            }
            if (!field.querySelector(searchSelector)) fields.add(field);
        }
        if (!fields.size) return;
        container.dataset.mobileFiltersReady = 'true';
        container.classList.add('cbed-mobile-filters');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cbed-mobile-filter-toggle';
        button.textContent = 'Filters';
        const ids = [];
        for (const field of fields) {
            field.classList.add('cbed-mobile-filter-field');
            field.id ||= 'cbed-filter-' + ++filterId;
            ids.push(field.id);
        }
        button.setAttribute('aria-controls', ids.join(' '));
        const update = open => {
            container.classList.toggle('cbed-mobile-filters-open', open);
            button.setAttribute('aria-expanded', String(open));
            button.textContent = open ? 'Hide filters' : 'Filters';
        };
        button.addEventListener('click', () => update(!container.classList.contains('cbed-mobile-filters-open')));
        update(false);
        container.prepend(button);
    }

    function editor(form) {
        if (form.classList.contains('cbed-mobile-editor') || !form.querySelector('input:not([type="hidden"]),select,textarea')) return;
        if (/^(get|dialog)$/i.test(form.getAttribute('method') ?? '')) return;
        const dialog = form.closest('dialog, [role="dialog"], .fixed.inset-0');
        if (!dialog && !form.hasAttribute('data-mobile-inline-editor')) return;
        if (dialog) dialog.classList.add('cbed-mobile-editor-shell');
        form.classList.add('cbed-mobile-editor');
        if (dialog && dialog !== form && dialog.tagName !== 'DIALOG') {
            let pane = form;
            while (pane.parentElement !== dialog) pane = pane.parentElement;
            pane.classList.add('cbed-mobile-editor-pane');
        }
        const submit = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')].at(-1);
        if (!submit) return;
        const actions = submit.closest('footer') ?? submit.parentElement;
        actions.classList.add('cbed-mobile-editor-actions');
    }

    function enhance() {
        if (!viewport.matches) return;
        document.querySelectorAll('.cbed-mobile-frame form').forEach(editor);
        for (const button of document.querySelectorAll('.cbed-mobile-frame .cbed-save-button')) {
            if (button.closest('.cbed-mobile-editor')) continue;
            const shell = button.closest('dialog, .fixed.inset-0');
            if (!shell || !shell.querySelector('input:not([type="hidden"]), select, textarea')) continue;
            let pane = button;
            while (pane.parentElement !== shell) pane = pane.parentElement;
            shell.classList.add('cbed-mobile-editor-shell');
            pane.classList.add('cbed-mobile-editor');
            if (shell.tagName !== 'DIALOG') pane.classList.add('cbed-mobile-editor-pane');
            button.parentElement.classList.add('cbed-mobile-editor-actions');
        }
        document.querySelectorAll('.cbed-mobile-frame table').forEach(cardTable);
        document.querySelectorAll('.cbed-mobile-frame [data-auto-filters], .cbed-mobile-frame [data-mobile-filters]').forEach(filters);
        for (const search of document.querySelectorAll('.cbed-mobile-frame ' + searchSelector.split(', ').join(', .cbed-mobile-frame '))) {
            if (search.closest('dialog, .cbed-mobile-editor')) continue;
            const group = search.closest('form, .grid');
            if (group?.querySelectorAll('select').length >= 2) filters(group);
        }
        for (const control of document.querySelectorAll('.cbed-mobile-frame select.filter-input, .cbed-mobile-frame input.filter-input')) {
            if (!control.closest('table, dialog, .cbed-mobile-editor')) filters(control.closest('.grid'));
        }
    }

    let queued = false;
    const scheduleEnhancement = () => {
        if (!viewport.matches || queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; enhance(); });
    };
    viewport.addEventListener('change', () => {
        if (viewport.matches) scheduleEnhancement();
        else document.querySelectorAll('.cbed-record-toggle-cell').forEach(cell => cell.remove());
    });
    new MutationObserver(changes => {
        if (changes.some(change => [...change.addedNodes].some(node =>
            node instanceof Element && (node.matches('tr, table, form, tbody') || node.querySelector('tr, table, form'))
        ))) scheduleEnhancement();
    }).observe(document.body, {childList: true, subtree: true});
    enhance();

    document.querySelector('[data-mobile-search]')?.addEventListener('click', event => {
        const search = [...document.querySelectorAll(searchSelector)].find(input =>
            !input.closest('dialog, .cbed-mobile-editor') && input.getClientRects().length
        );
        if (!search) return;
        event.preventDefault();
        search.focus({preventScroll: true});
        search.scrollIntoView({block: 'center'});
    });
})();
