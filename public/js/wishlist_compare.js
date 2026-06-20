jQuery(document).ready(function ($) {
    const state = (window.FuremirState = window.FuremirState || {});
    state.wishlistProductIds = (state.wishlistProductIds || []).map(Number);
    state.compareProductIds = (state.compareProductIds || []).map(Number);

    function showToast(message, type) {
        if (typeof Toastify === 'undefined') return;

        Toastify({
            className: `toastify-custom ${type}`,
            text: message,
            close: true,
            duration: 3000,
        }).showToast();
    }

    function setCount(selector, count) {
        const element = $(selector);
        element.text(count > 0 ? count : '');
        element.toggleClass('bg-black', count > 0);
    }

    function setId(listName, productId, active) {
        const id = Number(productId);
        const ids = new Set((state[listName] || []).map(Number));
        active ? ids.add(id) : ids.delete(id);
        state[listName] = Array.from(ids);
    }

    function refreshButtons() {
        $('.js-wishlist-toggle').each(function () {
            const productId = Number($(this).data('product-id'));
            const active = state.wishlistProductIds.includes(productId);
            $(this).toggleClass('active', active);
            $(this).find('span').text(active ? 'In wishlist' : 'Add to wishlist');
        });

        $('.js-compare-toggle').each(function () {
            const productId = Number($(this).data('product-id'));
            const active = state.compareProductIds.includes(productId);
            $(this).toggleClass('active', active);
            $(this).find('span').text(active ? 'In compare' : 'Compare');
        });

        setCount('.js-wishlist-count', state.wishlistProductIds.length);
        setCount('.js-compare-count', state.compareProductIds.length);
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

    function handleAjaxError(xhr, fallbackMessage) {
        if (xhr.status === 401 && xhr.responseJSON?.redirect) {
            window.location.href = xhr.responseJSON.redirect;
            return;
        }

        const message = xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {})[0]?.[0] || fallbackMessage;
        showToast(message, 'error');
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

    $(document).on('click', '.js-wishlist-toggle', function (event) {
        event.preventDefault();
        const button = $(this);
        if (button.hasClass('is-loading')) return;

        const productId = Number(button.data('product-id') || button.closest('form').find('[name="product_id"]').val());
        const active = state.wishlistProductIds.includes(productId);

        button.addClass('is-loading').prop('disabled', true);

        ajaxRequest({
            url: active ? `/wishlist/${productId}` : '/wishlist',
            method: active ? 'DELETE' : 'POST',
            data: active ? {} : { product_id: productId },
        })
            .done(function (response) {
                setId('wishlistProductIds', productId, !!response.wishlisted);
                if (typeof response.count === 'number') {
                    state.wishlistCount = response.count;
                }
                refreshButtons();
                showToast(response.message || 'Wishlist updated.', 'success');
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Could not update wishlist.');
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(document).on('click', '.js-wishlist-remove', function (event) {
        event.preventDefault();
        const button = $(this);
        const productId = Number(button.data('product-id'));
        if (!productId || button.hasClass('is-loading')) return;

        button.addClass('is-loading').prop('disabled', true);

        ajaxRequest({
            url: `/wishlist/${productId}`,
            method: 'DELETE',
        })
            .done(function (response) {
                setId('wishlistProductIds', productId, false);
                refreshButtons();
                button.closest('.col-lg-3, .wishlist-card').fadeOut(180, function () {
                    $(this).remove();
                });
                showToast(response.message || 'Removed from wishlist.', 'success');
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Could not remove wishlist item.');
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(document).on('click', '.js-wishlist-move-to-cart', function () {
        const button = $(this);
        const productId = Number(button.data('product-id'));
        if (!productId || button.hasClass('is-loading')) return;

        button.addClass('is-loading').prop('disabled', true);
        addCartItem({
            sku: button.data('sku'),
            quantities: 1,
            color_name: button.data('color-name'),
            color_hex: button.data('color-code'),
            image: button.data('image'),
        });

        ajaxRequest({
            url: `/wishlist/${productId}`,
            method: 'DELETE',
        })
            .done(function (response) {
                setId('wishlistProductIds', productId, false);
                refreshButtons();
                button.closest('.col-lg-3').fadeOut(180, function () {
                    $(this).remove();
                });
                showToast(response.message || 'Moved to cart.', 'success');
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Item was added to cart, but wishlist could not be updated.');
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(document).on('click', '.js-compare-toggle', function (event) {
        event.preventDefault();
        const button = $(this);
        if (button.hasClass('is-loading')) return;

        const productId = Number(button.data('product-id') || button.closest('form').find('[name="product_id"]').val());
        const active = state.compareProductIds.includes(productId);

        button.addClass('is-loading').prop('disabled', true);

        ajaxRequest({
            url: active ? `/compare/${productId}` : '/compare',
            method: active ? 'DELETE' : 'POST',
            data: active ? {} : { product_id: productId },
        })
            .done(function (response) {
                state.compareProductIds = (response.ids || []).map(Number);
                state.compareCount = response.count || state.compareProductIds.length;
                refreshButtons();
                showToast(response.message || 'Compare updated.', 'success');
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Could not update compare.');
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(document).on('click', '.js-compare-remove', function (event) {
        event.preventDefault();
        const button = $(this);
        const productId = Number(button.data('product-id'));
        if (!productId || button.hasClass('is-loading')) return;

        button.addClass('is-loading').prop('disabled', true);

        ajaxRequest({
            url: `/compare/${productId}`,
            method: 'DELETE',
        })
            .done(function (response) {
                state.compareProductIds = (response.ids || []).map(Number);
                refreshButtons();
                window.location.reload();
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Could not remove compare product.');
            });
    });

    $(document).on('click', '.js-compare-clear', function (event) {
        event.preventDefault();

        ajaxRequest({
            url: '/compare',
            method: 'DELETE',
        })
            .done(function () {
                state.compareProductIds = [];
                refreshButtons();
                window.location.reload();
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Could not clear compare.');
            });
    });

    $(document).on('click', '.js-add-room-set', function () {
        const button = $(this);
        if (button.hasClass('is-loading')) return;

        button.addClass('is-loading').prop('disabled', true);

        ajaxRequest({
            url: '/recommendations/combo-cart-items',
            method: 'POST',
            data: {
                product_ids: button.data('product-ids') || [],
            },
        })
            .done(function (response) {
                const items = response.items || [];
                items.forEach((item) => addCartItem(item));

                if (items.length > 0) {
                    showToast(`${items.length} room item(s) added to cart.`, 'success');
                }

                if ((response.skipped || []).length > 0) {
                    showToast(`${response.skipped.length} item(s) could not be added because of stock or availability.`, 'error');
                }
            })
            .fail(function (xhr) {
                handleAjaxError(xhr, 'Could not add room set.');
            })
            .always(function () {
                button.removeClass('is-loading').prop('disabled', false);
            });
    });

    $(document).on('furemir:catalog-updated', refreshButtons);
    refreshButtons();
});
