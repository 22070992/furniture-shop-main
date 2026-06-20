(function () {
    'use strict';

    const root = document.getElementById('visual-feedback-root');
    if (!root) return;

    const state = {
        active: false,
        hovered: null,
        selected: null,
        payload: null,
        screenshot: null,
    };

    const trigger = root.querySelector('.vf-trigger');
    const modebar = root.querySelector('.vf-modebar');
    const highlight = root.querySelector('.vf-highlight');
    const label = root.querySelector('.vf-label');
    const modal = root.querySelector('.vf-modal');
    const form = root.querySelector('.vf-form');
    const alertBox = root.querySelector('.vf-alert');
    const preview = root.querySelector('.vf-preview');

    const isAuthenticated = root.dataset.authenticated === '1';
    const loginUrl = root.dataset.loginUrl || '/login';
    const storeUrl = root.dataset.storeUrl || '/feedback';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function isFeedbackUi(element) {
        return Boolean(element && element.closest('[data-visual-feedback-ui]'));
    }

    function escapeCss(value) {
        if (window.CSS && CSS.escape) return CSS.escape(value);
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    function escapeAttr(value) {
        return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function elementLabel(element) {
        if (!element) return 'element';
        const component = element.dataset.component || element.closest('[data-component]')?.dataset.component;
        if (component) return component.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
        if (element.matches('nav, header')) return 'navigation';
        if (element.matches('img, picture')) return 'image';
        if (element.matches('button, [role="button"]')) return 'button';
        if (element.matches('input, textarea, select')) return 'input';
        if (element.matches('a')) return 'link';
        if (element.matches('h1, h2, h3, h4, h5, h6')) return 'heading';
        if (element.closest('[data-product-id], .product-wrap, .product-card')) return 'product-card';
        return element.tagName.toLowerCase();
    }

    function uniqueSelector(selector) {
        try {
            return document.querySelectorAll(selector).length === 1;
        } catch (error) {
            return false;
        }
    }

    function meaningfulClasses(element) {
        return Array.from(element.classList || [])
            .filter((name) => {
                if (name.length > 40) return false;
                return !/^(col|row|mt|mb|ms|me|pt|pb|ps|pe|p|m|d|w|h|text|bg|border|rounded|shadow|container|align|justify|gap)-/.test(name);
            })
            .slice(0, 3);
    }

    function stableSelector(element) {
        const stableAttrs = ['data-testid', 'data-feedback-id'];
        for (const attr of stableAttrs) {
            const value = element.getAttribute(attr);
            if (value) {
                const selector = `[${attr}="${escapeAttr(value)}"]`;
                if (uniqueSelector(selector)) return selector;
            }
        }

        if (element.id) {
            const selector = `#${escapeCss(element.id)}`;
            if (uniqueSelector(selector)) return selector;
        }

        for (const attr of ['name', 'aria-label']) {
            const value = element.getAttribute(attr);
            if (value) {
                const selector = `${element.tagName.toLowerCase()}[${attr}="${escapeAttr(value)}"]`;
                if (uniqueSelector(selector)) return selector;
            }
        }

        const classes = meaningfulClasses(element);
        if (classes.length) {
            const selector = `${element.tagName.toLowerCase()}.${classes.map(escapeCss).join('.')}`;
            if (uniqueSelector(selector)) return selector;
        }

        const parts = [];
        let current = element;
        while (current && current.nodeType === Node.ELEMENT_NODE && current !== document.body && parts.length < 7) {
            let part = current.tagName.toLowerCase();
            const feedbackId = current.getAttribute('data-feedback-id');
            if (feedbackId) {
                parts.unshift(`[data-feedback-id="${escapeAttr(feedbackId)}"]`);
                break;
            }
            if (current.id) {
                parts.unshift(`#${escapeCss(current.id)}`);
                break;
            }
            const siblings = Array.from(current.parentNode ? current.parentNode.children : []).filter(
                (sibling) => sibling.tagName === current.tagName,
            );
            if (siblings.length > 1) {
                part += `:nth-of-type(${siblings.indexOf(current) + 1})`;
            }
            parts.unshift(part);
            current = current.parentElement;
        }

        return parts.join(' > ');
    }

    function xpath(element) {
        const parts = [];
        let current = element;
        while (current && current.nodeType === Node.ELEMENT_NODE) {
            let index = 1;
            let sibling = current.previousElementSibling;
            while (sibling) {
                if (sibling.tagName === current.tagName) index += 1;
                sibling = sibling.previousElementSibling;
            }
            parts.unshift(`${current.tagName.toLowerCase()}[${index}]`);
            current = current.parentElement;
        }
        return '/' + parts.join('/');
    }

    function collectAttributes(element) {
        const attrs = {};
        Array.from(element.attributes || []).forEach((attr) => {
            if (/^(value|checked|selected)$/i.test(attr.name)) return;
            if (/password|token|secret|card/i.test(attr.name)) return;
            attrs[attr.name] = attr.value;
        });
        return attrs;
    }

    function selectedPayload(element) {
        const rect = element.getBoundingClientRect();
        return {
            page_url: window.location.href,
            page_path: window.location.pathname,
            page_title: document.title,
            route_name: root.dataset.currentRoute || '',
            element_tag: element.tagName.toLowerCase(),
            element_text: (element.innerText || element.alt || element.getAttribute('aria-label') || '').trim().slice(0, 5000),
            element_id: element.id || '',
            element_classes: Array.from(element.classList || []),
            css_selector: stableSelector(element),
            xpath: xpath(element),
            element_attributes: collectAttributes(element),
            element_rect: {
                x: Math.round(rect.left + window.scrollX),
                y: Math.round(rect.top + window.scrollY),
                viewport_x: Math.round(rect.left),
                viewport_y: Math.round(rect.top),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            },
            scroll_position: {
                x: Math.round(window.scrollX),
                y: Math.round(window.scrollY),
            },
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight,
                device_pixel_ratio: window.devicePixelRatio || 1,
            },
            device_type: window.innerWidth < 768 ? 'mobile' : window.innerWidth < 1024 ? 'tablet' : 'desktop',
            browser_info: {
                user_agent: navigator.userAgent,
                language: navigator.language,
                platform: navigator.platform,
            },
        };
    }

    function updateHighlight(element) {
        if (!element || isFeedbackUi(element)) return;
        const rect = element.getBoundingClientRect();
        highlight.style.left = `${rect.left}px`;
        highlight.style.top = `${rect.top}px`;
        highlight.style.width = `${rect.width}px`;
        highlight.style.height = `${rect.height}px`;
        highlight.classList.remove('d-none');
        label.textContent = elementLabel(element);
        label.style.left = `${Math.max(8, rect.left)}px`;
        label.style.top = `${Math.max(8, rect.top - 28)}px`;
        label.classList.remove('d-none');
    }

    function hideHighlight() {
        highlight.classList.add('d-none');
        label.classList.add('d-none');
    }

    function maskSensitiveClone(documentClone) {
        documentClone
            .querySelectorAll('input[type="password"], [data-sensitive], [name*="token" i], [name*="password" i], [name*="card" i]')
            .forEach((element) => {
                if ('value' in element) element.value = '';
                element.textContent = '[hidden]';
                element.setAttribute('data-sensitive-masked', 'true');
            });
    }

    function annotateCanvas(canvas, rect) {
        const annotated = document.createElement('canvas');
        const dpr = window.devicePixelRatio || 1;
        annotated.width = canvas.width;
        annotated.height = canvas.height;
        const ctx = annotated.getContext('2d');
        ctx.drawImage(canvas, 0, 0);
        ctx.strokeStyle = '#f97316';
        ctx.lineWidth = Math.max(3, 3 * dpr);
        ctx.fillStyle = 'rgba(249, 115, 22, 0.14)';
        ctx.fill(rect.viewport_x * dpr, rect.viewport_y * dpr, rect.width * dpr, rect.height * dpr);
        ctx.strokeRect(rect.viewport_x * dpr, rect.viewport_y * dpr, rect.width * dpr, rect.height * dpr);
        return annotated;
    }

    function fallbackAnnotation(payload) {
        const rect = payload.element_rect;
        const dpr = window.devicePixelRatio || 1;
        const canvas = document.createElement('canvas');
        canvas.width = window.innerWidth * dpr;
        canvas.height = window.innerHeight * dpr;
        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, window.innerWidth, window.innerHeight);
        ctx.fillStyle = '#182433';
        ctx.font = '16px sans-serif';
        ctx.fillText(document.title || 'Visual Feedback', 24, 34);
        ctx.font = '12px sans-serif';
        ctx.fillStyle = '#66758a';
        ctx.fillText(window.location.href.slice(0, 120), 24, 56);
        ctx.fillText(payload.css_selector.slice(0, 120), 24, 78);
        ctx.fillStyle = 'rgba(249, 115, 22, 0.14)';
        ctx.strokeStyle = '#f97316';
        ctx.lineWidth = 3;
        ctx.fillRect(rect.viewport_x, rect.viewport_y, rect.width, rect.height);
        ctx.strokeRect(rect.viewport_x, rect.viewport_y, rect.width, rect.height);
        return canvas.toDataURL('image/png');
    }

    async function foreignObjectScreenshot(payload) {
        const width = window.innerWidth;
        const height = window.innerHeight;
        const dpr = window.devicePixelRatio || 1;
        const clone = document.documentElement.cloneNode(true);
        clone.querySelectorAll('script, [data-visual-feedback-ui]').forEach((element) => element.remove());
        maskSensitiveClone(clone);

        const head = clone.querySelector('head');
        if (head) {
            const base = document.createElement('base');
            base.setAttribute('href', window.location.href);
            head.prepend(base);
        }

        const body = clone.querySelector('body');
        if (body) {
            body.style.transform = `translate(${-window.scrollX}px, ${-window.scrollY}px)`;
            body.style.transformOrigin = 'top left';
            body.style.width = `${document.documentElement.scrollWidth}px`;
            body.style.minHeight = `${document.documentElement.scrollHeight}px`;
        }

        const serialized = new XMLSerializer().serializeToString(clone);
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                <foreignObject width="100%" height="100%">
                    ${serialized}
                </foreignObject>
            </svg>`;
        const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);

        try {
            const image = await new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = url;
            });
            const canvas = document.createElement('canvas');
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            const ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);
            ctx.drawImage(image, 0, 0, width, height);
            return annotateCanvas(canvas, payload.element_rect).toDataURL('image/png');
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async function screenshot(payload) {
        const rect = payload.element_rect;
        if (window.html2canvas) {
            const canvas = await window.html2canvas(document.documentElement, {
                x: window.scrollX,
                y: window.scrollY,
                width: window.innerWidth,
                height: window.innerHeight,
                windowWidth: window.innerWidth,
                windowHeight: window.innerHeight,
                useCORS: true,
                backgroundColor: '#ffffff',
                ignoreElements: (element) => isFeedbackUi(element),
                onclone: maskSensitiveClone,
            });
            return annotateCanvas(canvas, rect).toDataURL('image/png');
        }

        try {
            return await foreignObjectScreenshot(payload);
        } catch (error) {
            return fallbackAnnotation(payload);
        }
    }

    function showAlert(message, type) {
        alertBox.textContent = message;
        alertBox.className = `vf-alert ${type || 'info'}`;
        alertBox.classList.remove('d-none');
    }

    function clearAlert() {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    function openModal() {
        clearAlert();
        root.querySelector('.vf-selected-page').textContent = state.payload.page_title || state.payload.page_path;
        root.querySelector('.vf-selected-element').textContent = `${state.payload.element_tag} · ${state.payload.css_selector}`;
        if (state.screenshot) {
            preview.src = state.screenshot;
            preview.classList.remove('d-none');
        }
        modal.classList.remove('d-none');
        document.body.classList.add('vf-modal-open');
        setTimeout(() => form.querySelector('[name="title"]')?.focus(), 20);
    }

    function closeModal() {
        modal.classList.add('d-none');
        document.body.classList.remove('vf-modal-open');
        form.reset();
        clearAlert();
    }

    function startMode() {
        if (!isAuthenticated) {
            window.location.href = `${loginUrl}?redirect=${encodeURIComponent(window.location.href)}`;
            return;
        }
        state.active = true;
        state.hovered = null;
        state.selected = null;
        document.body.classList.add('vf-mode-active');
        modebar.classList.remove('d-none');
        trigger.classList.add('d-none');
    }

    function stopMode() {
        state.active = false;
        state.hovered = null;
        hideHighlight();
        modebar.classList.add('d-none');
        trigger.classList.remove('d-none');
        document.body.classList.remove('vf-mode-active');
    }

    async function chooseElement(element) {
        if (!element || isFeedbackUi(element)) return;
        state.selected = element;
        state.payload = selectedPayload(element);
        stopMode();
        showAlert('Đang tạo ảnh chụp khu vực được chọn...', 'info');
        modal.classList.remove('d-none');
        try {
            state.screenshot = await screenshot(state.payload);
        } catch (error) {
            state.screenshot = null;
        }
        openModal();
    }

    function appendPayload(formData) {
        Object.entries(state.payload || {}).forEach(([key, value]) => {
            if (value === null || value === undefined) return;
            if (typeof value === 'object') {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, value);
            }
        });
        if (state.screenshot) {
            formData.append('screenshot_data', state.screenshot);
        }
    }

    async function submitFeedback(event) {
        event.preventDefault();
        if (!state.payload?.css_selector) {
            showAlert('Bạn cần chọn một element trước khi gửi.', 'error');
            return;
        }

        const submitButton = form.querySelector('[type="submit"]');
        submitButton.disabled = true;
        submitButton.textContent = 'Đang gửi...';

        const formData = new FormData(form);
        appendPayload(formData);

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Không gửi được phản hồi.';
                throw new Error(errors);
            }
            showAlert(`${data.message} Mã phản hồi: #${data.feedback_id}`, 'success');
            setTimeout(() => closeModal(), 1400);
        } catch (error) {
            showAlert(error.message || 'Không gửi được phản hồi.', 'error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Gửi phản hồi';
        }
    }

    function onPointerMove(event) {
        if (!state.active || isFeedbackUi(event.target)) return;
        state.hovered = event.target;
        updateHighlight(state.hovered);
    }

    function onClickCapture(event) {
        if (!state.active || isFeedbackUi(event.target)) return;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        chooseElement(event.target);
    }

    function onKeydown(event) {
        if (!state.active && modal.classList.contains('d-none')) return;

        if (event.key === 'Escape') {
            if (state.active) stopMode();
            if (!modal.classList.contains('d-none')) closeModal();
        }

        if (state.active && event.key === 'Enter' && state.hovered) {
            event.preventDefault();
            chooseElement(state.hovered);
        }

        if (state.active && event.key === 'ArrowUp' && state.hovered?.parentElement && !isFeedbackUi(state.hovered.parentElement)) {
            event.preventDefault();
            state.hovered = state.hovered.parentElement;
            updateHighlight(state.hovered);
        }

        if (state.active && event.key === 'ArrowDown' && state.hovered?.firstElementChild) {
            event.preventDefault();
            state.hovered = state.hovered.firstElementChild;
            updateHighlight(state.hovered);
        }

        if (!modal.classList.contains('d-none') && event.key === 'Tab') {
            const focusable = Array.from(modal.querySelectorAll('button, input, textarea, select, a[href]')).filter(
                (element) => !element.disabled && element.offsetParent !== null,
            );
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    trigger.addEventListener('click', startMode);
    root.querySelector('.vf-exit').addEventListener('click', stopMode);
    root.querySelector('.vf-close').addEventListener('click', closeModal);
    root.querySelector('.vf-cancel').addEventListener('click', closeModal);
    root.querySelector('.vf-reselect').addEventListener('click', function () {
        closeModal();
        startMode();
    });
    form.addEventListener('submit', submitFeedback);
    document.addEventListener('pointermove', onPointerMove, true);
    document.addEventListener('click', onClickCapture, true);
    document.addEventListener('keydown', onKeydown, true);
})();
