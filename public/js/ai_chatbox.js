document.addEventListener('DOMContentLoaded', function () {
    const widget = document.querySelector('[data-ai-chatbox]');
    if (!widget) return;

    const endpoint = widget.dataset.endpoint;
    const toggle = widget.querySelector('[data-ai-chat-toggle]');
    const closeButton = widget.querySelector('[data-ai-chat-close]');
    const panel = widget.querySelector('[data-ai-chat-panel]');
    const form = widget.querySelector('[data-ai-chat-form]');
    const input = widget.querySelector('[data-ai-chat-input]');
    const messages = widget.querySelector('[data-ai-chat-messages]');
    const quickWrap = widget.querySelector('[data-ai-chat-quick]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const currency = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
    const conversationHistory = [];

    function openChat() {
        panel.hidden = false;
        widget.classList.add('open');
        input.focus();
        scrollToBottom();
    }

    function closeChat() {
        widget.classList.remove('open');
        panel.hidden = true;
    }

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function appendMessage(type, text) {
        const item = document.createElement('div');
        item.className = `ai-chat-message ${type}`;
        item.innerHTML = `<p>${escapeHtml(text)}</p>`;
        messages.appendChild(item);
        scrollToBottom();
        return item;
    }

    function appendTyping() {
        const item = document.createElement('div');
        item.className = 'ai-chat-message bot typing';
        item.innerHTML = '<p><span></span><span></span><span></span></p>';
        messages.appendChild(item);
        scrollToBottom();
        return item;
    }

    function appendProducts(products) {
        if (!products || products.length === 0) return;

        const grid = document.createElement('div');
        grid.className = 'ai-chat-products';

        products.forEach(function (product) {
            const card = document.createElement('a');
            card.className = 'ai-chat-product';
            card.href = product.url;
            card.innerHTML = `
                <img src="${product.image || '/images/default/no-image.png'}" alt="${escapeHtml(product.name)}">
                <span>
                    <strong>${escapeHtml(product.name)}</strong>
                    <small>${escapeHtml([product.category, product.color].filter(Boolean).join(' · '))}</small>
                    <em>${currency.format(product.price)}đ</em>
                </span>
            `;
            grid.appendChild(card);
        });

        messages.appendChild(grid);
        scrollToBottom();
    }

    function renderQuickReplies(replies) {
        if (!replies || replies.length === 0) return;
        quickWrap.innerHTML = '';
        replies.forEach(function (reply) {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.prompt = reply;
            button.textContent = reply;
            quickWrap.appendChild(button);
        });
    }

    function remember(role, content) {
        conversationHistory.push({ role, content });
        while (conversationHistory.length > 10) {
            conversationHistory.shift();
        }
    }

    function setLoading(isLoading) {
        input.disabled = isLoading;
        form.querySelector('button').disabled = isLoading;
    }

    async function sendMessage(message) {
        const cleanMessage = message.trim();
        if (!cleanMessage) return;

        openChat();
        appendMessage('user', cleanMessage);
        remember('user', cleanMessage);
        input.value = '';
        setLoading(true);
        const typing = appendTyping();

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message: cleanMessage, history: conversationHistory.slice(0, -1) }),
            });

            if (!response.ok) {
                throw new Error('Chat request failed');
            }

            const data = await response.json();
            typing.remove();
            const botReply = data.reply || 'Mình đã nhận được câu hỏi của bạn.';
            appendMessage('bot', botReply);
            remember('assistant', botReply);
            appendProducts(data.products);
            renderQuickReplies(data.quick_replies);
        } catch (error) {
            typing.remove();
            appendMessage('bot', 'Mình chưa trả lời được lúc này. Bạn thử lại sau vài giây nhé.');
        } finally {
            setLoading(false);
            input.focus();
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    toggle.addEventListener('click', function () {
        if (widget.classList.contains('open')) {
            closeChat();
        } else {
            openChat();
        }
    });

    closeButton.addEventListener('click', closeChat);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value);
    });

    quickWrap.addEventListener('click', function (event) {
        const button = event.target.closest('[data-prompt]');
        if (!button) return;
        sendMessage(button.dataset.prompt);
    });
});
