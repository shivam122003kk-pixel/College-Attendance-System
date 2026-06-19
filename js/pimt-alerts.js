(function () {
    const styles = `
        .pimt-toast-stack {
            position: fixed;
            top: 22px;
            right: 22px;
            z-index: 2147483000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: min(380px, calc(100vw - 32px));
            pointer-events: none;
        }
        .pimt-toast {
            pointer-events: auto;
            display: grid;
            grid-template-columns: 42px 1fr 28px;
            align-items: start;
            gap: 12px;
            padding: 14px;
            color: #eaf2ff;
            background: linear-gradient(135deg, rgba(13,27,42,.96), rgba(26,45,68,.96));
            border: 1px solid rgba(255,255,255,.12);
            border-left: 4px solid var(--pimt-alert-color, #00b4d8);
            border-radius: 14px;
            box-shadow: 0 18px 50px rgba(0,0,0,.38);
            backdrop-filter: blur(16px);
            transform: translateX(18px);
            opacity: 0;
            animation: pimtToastIn .22s ease forwards;
        }
        .pimt-toast.is-leaving {
            animation: pimtToastOut .18s ease forwards;
        }
        .pimt-toast-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: var(--pimt-alert-color, #00b4d8);
            box-shadow: 0 10px 24px color-mix(in srgb, var(--pimt-alert-color, #00b4d8) 34%, transparent);
            font-size: 18px;
            font-weight: 800;
        }
        .pimt-toast-title {
            margin: 1px 0 4px;
            font: 800 14px/1.2 Outfit, Inter, system-ui, sans-serif;
            letter-spacing: 0;
        }
        .pimt-toast-message {
            color: rgba(234,242,255,.78);
            font: 500 13px/1.45 Inter, system-ui, sans-serif;
            overflow-wrap: anywhere;
        }
        .pimt-toast-close {
            border: 0;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            color: rgba(234,242,255,.75);
            background: rgba(255,255,255,.08);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }
        .pimt-toast-close:hover { background: rgba(255,255,255,.14); color: #fff; }
        .pimt-dialog-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2147482999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(5,12,24,.68);
            backdrop-filter: blur(8px);
            opacity: 0;
            animation: pimtFadeIn .16s ease forwards;
        }
        .pimt-dialog {
            width: min(430px, 100%);
            color: #eaf2ff;
            background: linear-gradient(135deg, rgba(13,27,42,.98), rgba(26,45,68,.98));
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 18px;
            box-shadow: 0 24px 70px rgba(0,0,0,.48);
            overflow: hidden;
            transform: translateY(10px) scale(.98);
            animation: pimtDialogIn .18s ease forwards;
        }
        .pimt-dialog-main {
            display: grid;
            grid-template-columns: 48px 1fr;
            gap: 14px;
            padding: 22px;
        }
        .pimt-dialog-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fca311;
            color: #101828;
            font-weight: 900;
            font-size: 22px;
        }
        .pimt-dialog-title {
            margin: 1px 0 8px;
            font: 800 18px/1.2 Outfit, Inter, system-ui, sans-serif;
        }
        .pimt-dialog-message {
            color: rgba(234,242,255,.76);
            font: 500 14px/1.55 Inter, system-ui, sans-serif;
            overflow-wrap: anywhere;
        }
        .pimt-dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 18px 18px;
            border-top: 1px solid rgba(255,255,255,.08);
        }
        .pimt-dialog-btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font: 800 13px/1 Inter, system-ui, sans-serif;
            cursor: pointer;
        }
        .pimt-dialog-cancel { color: #eaf2ff; background: rgba(255,255,255,.08); }
        .pimt-dialog-cancel:hover { background: rgba(255,255,255,.14); }
        .pimt-dialog-confirm { color: #101828; background: #fca311; }
        .pimt-dialog-confirm:hover { background: #ffd166; }
        .pimt-hidden-alert { display: none !important; }
        @keyframes pimtToastIn { to { opacity: 1; transform: translateX(0); } }
        @keyframes pimtToastOut { to { opacity: 0; transform: translateX(18px); } }
        @keyframes pimtFadeIn { to { opacity: 1; } }
        @keyframes pimtDialogIn { to { transform: translateY(0) scale(1); } }
        @media (max-width: 640px) {
            .pimt-toast-stack { top: 14px; right: 14px; left: 14px; width: auto; }
            .pimt-toast { grid-template-columns: 38px 1fr 28px; border-radius: 12px; }
            .pimt-toast-icon { width: 38px; height: 38px; }
            .pimt-dialog-main { grid-template-columns: 1fr; }
            .pimt-dialog-icon { width: 44px; height: 44px; }
        }
    `;

    const variants = {
        success: { title: 'Success', icon: 'OK', color: '#06d6a0' },
        warning: { title: 'Warning', icon: '!', color: '#fca311' },
        error: { title: 'Error', icon: '!', color: '#ef233c' },
        info: { title: 'Notice', icon: 'i', color: '#00b4d8' }
    };

    function injectStyles() {
        if (document.getElementById('pimt-alert-styles')) return;
        const style = document.createElement('style');
        style.id = 'pimt-alert-styles';
        style.textContent = styles;
        (document.head || document.documentElement).appendChild(style);
    }

    function getStack() {
        injectStyles();
        let stack = document.querySelector('.pimt-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'pimt-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-relevant', 'additions');
            (document.body || document.documentElement).appendChild(stack);
        }
        return stack;
    }

    function normalizeType(type) {
        if (type === 'danger') return 'error';
        return variants[type] ? type : 'info';
    }

    function show(message, type, title, timeout) {
        const cleanType = normalizeType(type || 'info');
        const data = variants[cleanType];
        const toast = document.createElement('div');
        toast.className = 'pimt-toast';
        toast.style.setProperty('--pimt-alert-color', data.color);
        toast.innerHTML = `
            <div class="pimt-toast-icon" aria-hidden="true">${data.icon}</div>
            <div>
                <div class="pimt-toast-title">${escapeHtml(title || data.title)}</div>
                <div class="pimt-toast-message">${escapeHtml(String(message || ''))}</div>
            </div>
            <button class="pimt-toast-close" type="button" aria-label="Close">&times;</button>
        `;
        const close = () => {
            toast.classList.add('is-leaving');
            setTimeout(() => toast.remove(), 190);
        };
        toast.querySelector('.pimt-toast-close').addEventListener('click', close);
        getStack().appendChild(toast);
        setTimeout(close, timeout || 4200);
        return toast;
    }

    function escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function confirmDialog(message, onConfirm) {
        injectStyles();
        const backdrop = document.createElement('div');
        backdrop.className = 'pimt-dialog-backdrop';
        backdrop.innerHTML = `
            <div class="pimt-dialog" role="dialog" aria-modal="true" aria-labelledby="pimt-dialog-title">
                <div class="pimt-dialog-main">
                    <div class="pimt-dialog-icon" aria-hidden="true">!</div>
                    <div>
                        <div class="pimt-dialog-title" id="pimt-dialog-title">Please Confirm</div>
                        <div class="pimt-dialog-message">${escapeHtml(String(message || 'Are you sure?'))}</div>
                    </div>
                </div>
                <div class="pimt-dialog-actions">
                    <button class="pimt-dialog-btn pimt-dialog-cancel" type="button">Cancel</button>
                    <button class="pimt-dialog-btn pimt-dialog-confirm" type="button">Continue</button>
                </div>
            </div>
        `;
        document.body.appendChild(backdrop);
        const close = () => backdrop.remove();
        backdrop.querySelector('.pimt-dialog-cancel').addEventListener('click', close);
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) close();
        });
        backdrop.querySelector('.pimt-dialog-confirm').addEventListener('click', () => {
            close();
            onConfirm();
        });
        backdrop.querySelector('.pimt-dialog-confirm').focus();
    }

    function followConfirmedAction(target) {
        if (!target) return;
        const link = target.closest ? target.closest('a[href]') : null;
        if (link) {
            window.location.href = link.href;
            return;
        }
        const form = target.closest ? target.closest('form') : null;
        if (form) form.submit();
    }

    function confirmLink(target, message) {
        confirmDialog(message, () => followConfirmedAction(target));
        return false;
    }

    function upgradeInlineAlerts() {
        document.querySelectorAll('.alert:not(.pimt-hidden-alert)').forEach((alertBox) => {
            const text = alertBox.textContent.trim();
            if (!text) return;
            let type = 'info';
            if (alertBox.classList.contains('alert-success')) type = 'success';
            if (alertBox.classList.contains('alert-warning')) type = 'warning';
            if (alertBox.classList.contains('alert-danger')) type = 'error';
            show(text, type);
            alertBox.classList.add('pimt-hidden-alert');
        });
    }

    window.PIMTAlert = {
        show,
        confirm: confirmDialog,
        confirmLink,
        upgradeInlineAlerts
    };

    window.alert = function (message) {
        show(message, 'info');
    };

    window.confirm = function (message) {
        const event = window.event;
        const target = event && event.currentTarget ? event.currentTarget : document.activeElement;
        if (event && event.preventDefault) event.preventDefault();
        confirmDialog(message, () => followConfirmedAction(target));
        return false;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', upgradeInlineAlerts);
    } else {
        upgradeInlineAlerts();
    }
})();
