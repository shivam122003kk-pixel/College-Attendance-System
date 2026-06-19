(function () {
    const actionLabels = new Set(['action', 'actions']);
    const terminalLabels = new Set(['activate', 'edit', 'delete']);
    const styles = `
        th.pimt-action-head,
        td.pimt-action-cell {
            text-align: center !important;
            white-space: nowrap;
        }
        th.pimt-action-head {
            min-width: 118px;
        }
        .pimt-action-queue {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            padding: 3px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.16);
            max-width: 132px;
        }
        .cams-table .pimt-action-queue {
            background: rgba(255, 255, 255, 0.035);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .pimt-action-queue a,
        .pimt-action-queue button,
        .pimt-action-queue .btn {
            width: auto;
            min-width: 32px;
            height: 34px;
            padding: 0 9px !important;
            border-radius: 10px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 0 !important;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            text-decoration: none !important;
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
            vertical-align: middle;
            white-space: nowrap;
        }
        .pimt-action-queue a:hover,
        .pimt-action-queue button:hover,
        .pimt-action-queue .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
        }
        .pimt-action-queue .fa-fw {
            width: auto;
        }
        .pimt-action-queue .pimt-action-edit,
        .pimt-action-queue .btn-primary {
            color: #fff !important;
            background: linear-gradient(135deg, #5c6bc0, #00b4d8) !important;
            border-color: rgba(92, 107, 192, 0.45) !important;
        }
        .pimt-action-queue .pimt-action-delete,
        .pimt-action-queue .btn-danger {
            color: #fff !important;
            background: linear-gradient(135deg, #ef233c, #b91c1c) !important;
            border-color: rgba(239, 35, 60, 0.45) !important;
        }
        .pimt-action-queue .pimt-action-activate,
        .pimt-action-queue .btn-success {
            color: #082218 !important;
            background: linear-gradient(135deg, #06d6a0, #7ee787) !important;
            border-color: rgba(6, 214, 160, 0.45) !important;
        }
        .pimt-action-queue .pimt-action-analytics {
            color: #fff !important;
            background: linear-gradient(135deg, #7c3aed, #9f67fa) !important;
            border-color: rgba(124, 58, 237, 0.45) !important;
        }
        .pimt-action-queue .pimt-action-remove,
        .pimt-action-queue .pimt-action-unlink {
            color: #fff !important;
            background: linear-gradient(135deg, #f97316, #ef233c) !important;
            border-color: rgba(249, 115, 22, 0.45) !important;
        }
        @media (max-width: 640px) {
            th.pimt-action-head { min-width: 96px; }
            .pimt-action-queue { gap: 6px; }
            .pimt-action-queue a,
            .pimt-action-queue button,
            .pimt-action-queue .btn {
                min-width: 32px;
                height: 32px;
                padding: 0 8px !important;
            }
        }
    `;

    function injectStyles() {
        if (document.getElementById('pimt-action-styles')) return;
        const style = document.createElement('style');
        style.id = 'pimt-action-styles';
        style.textContent = styles;
        (document.head || document.documentElement).appendChild(style);
    }

    function cleanLabel(value) {
        return value.replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function isTerminalAction(label) {
        return terminalLabels.has(cleanLabel(label));
    }

    function findTrailingActionStart(labels) {
        let start = labels.length;
        while (start > 0 && isTerminalAction(labels[start - 1])) start--;
        return labels.length - start >= 2 ? start : -1;
    }

    function classifyControl(control) {
        const text = cleanLabel(control.textContent || '');
        const href = cleanLabel(control.getAttribute('href') || '');
        const onclick = cleanLabel(control.getAttribute('onclick') || '');
        const icon = control.querySelector('i');
        const iconClass = icon ? icon.className : '';
        const haystack = `${text} ${href} ${onclick} ${iconClass}`.toLowerCase();

        if (haystack.includes('chart') || haystack.includes('analytics')) return 'pimt-action-analytics';
        if (haystack.includes('unlink') || haystack.includes('remove')) return 'pimt-action-unlink';
        if (haystack.includes('delete') || haystack.includes('trash')) return 'pimt-action-delete';
        if (haystack.includes('edit')) return 'pimt-action-edit';
        if (haystack.includes('activate') || haystack.includes('check')) return 'pimt-action-activate';
        return '';
    }

    function polishControl(control) {
        if (!control.matches('a, button, .btn')) return;
        const text = control.textContent.replace(/\s+/g, ' ').trim();
        if (text && !control.getAttribute('title')) control.setAttribute('title', text);
        const className = classifyControl(control);
        if (className) control.classList.add(className);
        if (control.querySelector('i')) {
            Array.from(control.childNodes).forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                    node.remove();
                }
            });
        }
    }

    function wrapActionCell(cell) {
        if (!cell || cell.classList.contains('pimt-action-cell')) return;
        const queue = document.createElement('div');
        queue.className = 'pimt-action-queue';

        while (cell.firstChild) {
            const node = cell.firstChild;
            if (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()) {
                node.remove();
                continue;
            }
            queue.appendChild(node);
        }

        queue.querySelectorAll('a, button, .btn').forEach(polishControl);
        cell.appendChild(queue);
        cell.classList.add('pimt-action-cell');
    }

    function mergeTerminalActions(table, headerRow, startIndex) {
        const headers = Array.from(headerRow.children);
        headers[startIndex].textContent = 'Actions';
        headers[startIndex].classList.add('pimt-action-head');
        for (let i = headers.length - 1; i > startIndex; i--) {
            headers[i].remove();
        }

        table.querySelectorAll('tbody tr').forEach((row) => {
            const cells = Array.from(row.children);
            if (cells.length <= startIndex) return;
            const actionCells = cells.slice(startIndex);
            const merged = actionCells[0];
            for (let i = 1; i < actionCells.length; i++) {
                while (actionCells[i].firstChild) {
                    merged.appendChild(document.createTextNode(' '));
                    merged.appendChild(actionCells[i].firstChild);
                }
                actionCells[i].remove();
            }
            wrapActionCell(merged);
        });
    }

    function styleActionColumn(table, headerRow, index) {
        const headers = Array.from(headerRow.children);
        if (headers[index]) headers[index].classList.add('pimt-action-head');
        table.querySelectorAll('tbody tr').forEach((row) => {
            const cell = row.children[index];
            if (cell) wrapActionCell(cell);
        });
    }

    function normalizeTables() {
        injectStyles();
        document.querySelectorAll('table').forEach((table) => {
            const headerRow = table.querySelector('thead tr:last-child');
            if (!headerRow || table.dataset.pimtActionsReady === '1') return;

            const headers = Array.from(headerRow.children);
            const labels = headers.map((header) => cleanLabel(header.textContent || ''));
            const actionIndex = labels.findIndex((label) => actionLabels.has(label));

            if (actionIndex >= 0) {
                styleActionColumn(table, headerRow, actionIndex);
            } else {
                const trailingStart = findTrailingActionStart(labels);
                if (trailingStart >= 0) mergeTerminalActions(table, headerRow, trailingStart);
            }

            table.dataset.pimtActionsReady = '1';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeTables);
    } else {
        normalizeTables();
    }

    window.PIMTActions = { refresh: normalizeTables };
})();
