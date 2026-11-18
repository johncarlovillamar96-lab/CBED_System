(() => {
    const scrollTargets = new WeakMap();
    const observed = new WeakSet();
    const controls = [
        'a', 'button', 'input', 'select', 'textarea', 'label', 'summary', '[contenteditable]',
        '[role="button"]', '[role="link"]', '[role="checkbox"]', '[role="switch"]',
        '[role="combobox"]', '[role="menuitem"]', '[aria-sort]', '[tabindex]', '[draggable="true"]',
        '[data-no-table-drag]',
    ].join(',');
    let gesture = null;
    let suppressClick = false;

    function tableAt(target) {
        return target instanceof Element ? target.closest('table') : null;
    }

    function isControl(target, table) {
        for (let element = target; element && element !== table; element = element.parentElement) {
            if (element.matches(controls) || element.hasAttribute('onclick')
                || element.getAttributeNames().some(name => /^(?:@click|x-on:click|wire:click)(?:\.|$)/.test(name))) {
                return true;
            }
        }
        return false;
    }

    function scrollContainer(table) {
        for (let element = table.parentElement; element && element !== document.body; element = element.parentElement) {
            if (['auto', 'scroll'].includes(getComputedStyle(element).overflowX)) return element;
            if (element.tagName === 'DIALOG' || element.tagName === 'MAIN') break;
        }
        return null;
    }

    function updateTable(table) {
        if (!table.isConnected) return;
        let container = scrollContainer(table);
        const parent = table.parentElement;
        if (!container && parent.clientWidth > 0 && table.getBoundingClientRect().width > parent.clientWidth + 1) {
            container = document.createElement('div');
            container.className = 'cbed-table-scroll-fallback';
            table.before(container);
            container.append(table);
        }
        table.classList.toggle('cbed-table-drag-surface', Boolean(container));
        if (container) scrollTargets.set(table, container);
        else scrollTargets.delete(table);
    }

    const resize = new ResizeObserver(entries => {
        entries.forEach(({target: table}) => updateTable(table));
    });

    function observeTable(table) {
        if (observed.has(table) || table.closest('#animal-monitoring-print, .pdf-report-table, [data-table-drag-disabled]')) return;
        observed.add(table);
        updateTable(table);
        resize.observe(table);
    }

    function tablesWithin(node, callback) {
        if (node.matches('table')) callback(node);
        node.querySelectorAll('table').forEach(callback);
    }

    document.querySelectorAll('table').forEach(observeTable);
    new MutationObserver(mutations => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node instanceof Element && node.isConnected) tablesWithin(node, observeTable);
            }
            for (const node of mutation.removedNodes) {
                if (node instanceof Element && !node.isConnected) {
                    tablesWithin(node, table => {
                        resize.unobserve(table);
                        observed.delete(table);
                        scrollTargets.delete(table);
                    });
                }
            }
        }
    }).observe(document.body, {childList: true, subtree: true});
    window.addEventListener('resize', () => document.querySelectorAll('table').forEach(table => {
        if (observed.has(table)) updateTable(table);
    }), {passive: true});

    function draggableContainer(target) {
        const table = tableAt(target);
        const container = table && scrollTargets.get(table);
        return container && container.scrollWidth > container.clientWidth + 1 && !isControl(target, table)
            ? {table, container}
            : null;
    }

    document.addEventListener('pointerdown', event => {
        if (event.pointerType !== 'mouse' || event.button !== 0 || event.defaultPrevented
            || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const target = draggableContainer(event.target);
        if (!target) return;
        gesture = {
            ...target, id: event.pointerId, x: event.clientX, y: event.clientY,
            scrollLeft: target.container.scrollLeft, dragging: false,
        };
    });

    document.addEventListener('selectstart', event => {
        if (gesture?.dragging && gesture.table.contains(event.target)) event.preventDefault();
    });

    document.addEventListener('pointermove', event => {
        if (!gesture || event.pointerId !== gesture.id) return;
        if (!(event.buttons & 1)) {
            endDrag(false);
            return;
        }
        const dx = event.clientX - gesture.x;
        if (!gesture.dragging) {
            const dy = Math.abs(event.clientY - gesture.y);
            if (dy >= 6 && dy > Math.abs(dx)) {
                endDrag(false);
                return;
            }
            if (Math.abs(dx) < 6) return;
            gesture.dragging = true;
            gesture.container.setPointerCapture(event.pointerId);
            document.documentElement.classList.add('cbed-table-dragging');
            const selection = window.getSelection();
            if (selection?.anchorNode && gesture.table.contains(selection.anchorNode)) selection.removeAllRanges();
        }
        event.preventDefault();
        gesture.container.scrollLeft = gesture.scrollLeft - dx;
    }, {capture: true, passive: false});

    function endDrag(shouldSuppressClick) {
        const current = gesture;
        gesture = null;
        if (!current?.dragging) return;
        document.documentElement.classList.remove('cbed-table-dragging');
        if (current.container.hasPointerCapture(current.id)) current.container.releasePointerCapture(current.id);
        if (shouldSuppressClick) {
            suppressClick = true;
            setTimeout(() => { suppressClick = false; }, 0);
        }
    }

    document.addEventListener('pointerup', event => {
        if (event.pointerId !== gesture?.id) return;
        endDrag(true);
    }, true);
    for (const type of ['pointercancel', 'lostpointercapture']) {
        document.addEventListener(type, event => {
            if (event.pointerId === gesture?.id) endDrag(false);
        }, true);
    }
    window.addEventListener('blur', () => endDrag(false));
    document.addEventListener('click', event => {
        if (!suppressClick || event.detail === 0) return;
        suppressClick = false;
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);
})();
