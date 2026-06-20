jQuery(function ($) {
    const root = $('[data-ai-interior-combo]');
    if (!root.length) return;

    let currentProducts = [];

    function showToast(message, type) {
        if (typeof Toastify === 'undefined') return;

        Toastify({
            className: `toastify-custom ${type}`,
            text: message,
            close: true,
            duration: 3000,
        }).showToast();
    }

    function ajaxRequest(options) {
        return $.ajax({
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                ...(options.headers || {}),
            },
        });
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function addCartItem(cartItem) {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        const existing = cart.find((item) => item.sku === cartItem.sku);

        if (existing) {
            existing.quantities += cartItem.quantities;
            existing.color_name = cartItem.color_name || existing.color_name;
            existing.color_hex = cartItem.color_hex || existing.color_hex;
            existing.image = cartItem.image || existing.image;
        } else {
            cart.push(cartItem);
        }

        localStorage.setItem('cart', JSON.stringify(cart));
        $('.js-total-cart').text(cart.length);
        $('.js-total-cart').toggleClass('bg-black', cart.length > 0);
    }

    function itemPayload(products) {
        return products.map((product) => ({
            product_id: product.product_id,
            sku: product.sku,
            quantity: 1,
        }));
    }

    function addComboProducts(products, button) {
        if (!products.length || button.hasClass('is-loading')) return;

        button.addClass('is-loading').prop('disabled', true);

        ajaxRequest({
            url: root.data('cart-url'),
            method: 'POST',
            data: {
                items: itemPayload(products),
            },
        })
            .done(function (response) {
                const items = response.items || [];
                items.forEach((item) => addCartItem(item));

                if (items.length > 0) {
                    showToast(`${items.length} combo item(s) added to cart.`, 'success');
                }

                if ((response.skipped || []).length > 0) {
                    showToast(`${response.skipped.length} item(s) could not be added.`, 'error');
                }
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'Could not add combo to cart.';
                showToast(message, 'error');
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false);
            });
    }

    function renderResult(response) {
        currentProducts = response.products || [];
        const result = root.find('[data-ai-combo-result]');
        const warnings = response.warnings || [];
        const notes = response.notes || [];

        if (!currentProducts.length) {
            result.html(`
                <div class="ai-combo-empty">
                    <strong>No combo found</strong>
                    <p>${escapeHtml(warnings[0] || 'Try a broader room, color or budget.')}</p>
                </div>
            `).prop('hidden', false);
            return;
        }

        const cards = currentProducts.map((product, index) => `
            <article class="ai-combo-card" data-product-id="${product.product_id}" data-sku="${escapeHtml(product.sku)}">
                <a href="${escapeHtml(product.url)}" class="ai-combo-image">
                    <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}">
                </a>
                <div class="ai-combo-card-body">
                    <span class="ai-combo-role">${escapeHtml(product.role || `Item ${index + 1}`)}</span>
                    <h3><a href="${escapeHtml(product.url)}">${escapeHtml(product.name)}</a></h3>
                    <p>${escapeHtml(product.reason)}</p>
                    <div class="ai-combo-meta">
                        <span>${escapeHtml(product.category)}</span>
                        <span>${escapeHtml(product.color_name || 'Default color')}</span>
                        <span>${escapeHtml(product.formatted_price)}</span>
                    </div>
                    <button type="button" class="ai-combo-add-one" data-ai-combo-add-one data-index="${index}">
                        Add item
                    </button>
                </div>
            </article>
        `).join('');

        result.html(`
            <div class="ai-combo-summary">
                <div>
                    <span class="ai-combo-source">${escapeHtml(response.source || 'fallback')}</span>
                    <strong>Total: ${escapeHtml(response.formatted_total)}</strong>
                    ${response.formatted_budget ? `<em>Budget: ${escapeHtml(response.formatted_budget)}</em>` : ''}
                </div>
                <button type="button" class="ai-combo-add-all" data-ai-combo-add-all>Add full combo</button>
            </div>
            ${warnings.length ? `<div class="ai-combo-warnings">${warnings.map((warning) => `<p>${escapeHtml(warning)}</p>`).join('')}</div>` : ''}
            ${notes.length ? `<div class="ai-combo-notes">${notes.map((note) => `<p>${escapeHtml(note)}</p>`).join('')}</div>` : ''}
            <div class="ai-combo-cards">${cards}</div>
        `).prop('hidden', false);
    }

    root.on('submit', '[data-ai-combo-form]', function (event) {
        event.preventDefault();

        const form = $(this);
        const button = form.find('[type="submit"]');
        const result = root.find('[data-ai-combo-result]');

        if (button.hasClass('is-loading')) return;
        button.addClass('is-loading').prop('disabled', true).text('Generating...');
        result.prop('hidden', false).html('<div class="ai-combo-loading">Finding real products...</div>');

        ajaxRequest({
            url: root.data('recommend-url'),
            method: 'POST',
            data: form.serialize(),
        })
            .done(renderResult)
            .fail(function (xhr) {
                const message = xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || 'Could not generate combo.';
                result.html(`<div class="ai-combo-empty"><strong>Request failed</strong><p>${escapeHtml(message)}</p></div>`);
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false).text('Generate combo');
            });
    });

    root.on('click', '[data-ai-combo-add-one]', function () {
        const index = Number($(this).data('index'));
        const product = currentProducts[index];
        if (!product) return;

        addComboProducts([product], $(this));
    });

    root.on('click', '[data-ai-combo-add-all]', function () {
        addComboProducts(currentProducts, $(this));
    });
});
