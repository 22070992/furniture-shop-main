jQuery(document).ready(function () {
    function showToast(message, type) {
        Toastify({
            className: `toastify-custom ${type}`,
            text: message,
            close: true,
            duration: 3000,
        }).showToast();
    }
    // init cart
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (cart.length > 0) {
        $('.js-total-cart').text(cart.length);
        $('.js-total-cart').addClass('bg-black');
    } else {
        $('.js-total-cart').removeClass('bg-black');
    }
    function convertCurrencyToNumber(currency) {
        if (currency.includes(',') || currency.includes('đ')) {
            return Number(currency.replace(/,|đ/g, ''));
        } else {
            return Number(currency);
        }
    }
    let formatter = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
    });

    function colorLine(item) {
        if (!item.color_name) return '';
        const swatch = item.color_hex
            ? `<span class="cart-color-swatch" style="background-color: ${item.color_hex}"></span>`
            : '';
        return `<small class="cart-color-line">${swatch}${item.color_name}</small>`;
    }

    function syncCart(callback) {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        if (cart.length === 0) {
            callback([]);
            return;
        }

        $.ajax({
            url: `/cart/async?cart=${encodeURIComponent(JSON.stringify(cart))}`,
            method: 'GET',
            success: function (response) {
                cart = response.cart;
                localStorage.setItem('cart', JSON.stringify(cart));
                callback(cart);
            },
            error: function (error) {
                console.log(error);
                callback(cart);
            },
        });
    }

    // load mini cart
    $('.header-action-cart').on('click', function () {
        syncCart(function (cart) {
                localStorage.setItem('cart', JSON.stringify(cart));
                if (cart.length === 0) {
                    $('.js-mini-cart-checkout-btn').addClass('disable');
                    $('.js-total-price').text('0đ');
                } else {
                    let html = '';
                    let total_price = 0;
                    for (let i = 0; i < cart.length; i++) {
                        item = cart[i];
                        total_price += item.unit_price * item.quantities;
                        html += `<li>
                                <div class="cart-item-info">
                                    <div class="cart-img">
                                        <a href="#"><img src="${item.image}" alt=""></a>
                                    </div>
                                    <div class="cart-title">
                                        <h4><a>${item.name}</a></h4>
                                        ${colorLine(item)}
                                        <span> ${item.quantities} × <span class="unit-price">${
                            formatter.format(item.unit_price) + 'đ'
                        }</span> </span>
                                    </div>
                                </div>
                                <div class="cart-delete">
                                    <a class="js-delete-cart-item" data-sku="${item.sku}"><i class="ti-close"></i></a>
                                </div>
                            </li>`;
                    }
                    $('#cart-list').html(html);
                    $('.js-total-price').text(formatter.format(total_price) + 'đ');
                    $('.js-mini-cart-checkout-btn').removeClass('disable');
                }
        });
    });
    // delete cart item
    $(document).on('click', '.js-delete-cart-item', function (e) {
        const sku = $(this).data('sku');
        if (!sku) return;
        e.stopImmediatePropagation();
        $(this).closest('li').remove();
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const newCart = cart.filter((item) => item.sku !== sku);
        localStorage.setItem('cart', JSON.stringify(newCart));
        $('.js-total-cart').text(newCart.length);

        let total_price = 0;
        if (newCart.length === 0) {
            $('.js-total-cart').removeClass('bg-black');
            $('.js-mini-cart-checkout-btn').addClass('disable');
            $('#cart-list').html(` <div class="empty-cart">
                    <img class="" src="/images/default/empty-cart.webp" />
                    <p>Your cart is currently empty.</p>
                </div>`);
        } else {
            for (let i = 0; i < newCart.length; i++) {
                item = newCart[i];
                total_price += item.unit_price * item.quantities;
            }
        }
        console.log(total_price);
        $('.js-total-price').text(formatter.format(total_price) + 'đ');
    });

    // cart page
    function renderCart(cart) {
        let html = '';
        if (cart.length === 0) {
            const emptyCart = `<div class="empty-cart">
            <img class="" src="/images/default/empty-cart.webp" />
            <p>Your cart is currently empty.</p>
        </div>`;
            $('#js-cart-table').html(emptyCart);
        } else {
            for (let i = 0; i < cart.length; i++) {
                let item = cart[i];
                html += `<div class="js-cart-item cart-item">
                <div class="product-cart-item">
                    <input class="js-check-cart-item" type="checkbox" name="" id="">
                    <img src="${item.image}" alt="">
                    <div class="info">
                        <p> ${item.name} </p>
                        ${colorLine(item)}
                        <p class="js-sku">${item.sku}</p>
                    </div>
                </div>
                <div><span class="js-unit-price">${formatter.format(item.unit_price)}đ</span></div>
                <div>
                    <div class="quantities-wrapper">
                        <button class="js-quantities-minus" ><i class="ti-minus"></i></button>
                        <input class="js-cart-quantities-input" type="number" value="${item.quantities}">
                        <button class="js-quantities-plus" ><i class="ti-plus"></i></button>
                    </div>
                </div>
                <div><span class="js-subtotal-price">${formatter.format(
                    item.unit_price * item.quantities,
                )}đ</span></div>
                <div class="js-delete-cart-item delete-item"><i class="ti-close"></i></div>
                </div>`;
            }
            $('#js-cart-table').html(html);
        }
    }
    function loadCart() {
        syncCart(renderCart);
    }
    loadCart();
    $(document).on('click', '.js-quantities-minus', function () {
        let quantities = $(this).siblings('input').val();
        quantities = Number(quantities) - 1;
        if (quantities < 1) quantities = 1;
        $(this).siblings('input').val(quantities);

        // set subtotal price
        const unit_price = convertCurrencyToNumber($(this).closest('.cart-item').find('.js-unit-price').text());
        $(this)
            .closest('.cart-item')
            .find('.js-subtotal-price')
            .text(formatter.format(unit_price * quantities) + 'đ');

        // update cart
        const sku = $(this).closest('.cart-item').find('.js-sku').text();
        console.log(sku);
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const newCart = cart.map((item) => {
            if (item.sku === sku) {
                item.quantities = quantities;
            }
            return item;
        });
        localStorage.setItem('cart', JSON.stringify(newCart));
        loadCartCheckout();
    });
    $(document).on('click', '.js-quantities-plus', function () {
        let quantities = $(this).siblings('input').val();
        quantities = Number(quantities) + 1;
        $(this).siblings('input').val(quantities);

        // set subtotal price
        const unit_price = convertCurrencyToNumber($(this).closest('.cart-item').find('.js-unit-price').text());

        $(this)
            .closest('.cart-item')
            .find('.js-subtotal-price')
            .text(formatter.format(unit_price * quantities) + 'đ');

        // update cart
        const sku = $(this).closest('.cart-item').find('.js-sku').text();
        console.log(sku);
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const newCart = cart.map((item) => {
            if (item.sku === sku) {
                item.quantities = quantities;
            }
            return item;
        });
        localStorage.setItem('cart', JSON.stringify(newCart));
        loadCartCheckout();
    });
    $(document).on('change', '.js-cart-quantities-input', function () {
        let quantities = $(this).val();
        if (quantities < 1) quantities = 1;
        $(this).val(quantities);

        // set subtotal price
        const unit_price = convertCurrencyToNumber($(this).closest('.cart-item').find('.js-unit-price').text());
        $(this)
            .closest('.cart-item')
            .find('.js-subtotal-price')
            .text(formatter.format(unit_price * quantities) + 'đ');

        // update cart
        const sku = $(this).closest('.cart-item').find('.js-sku').text();
        console.log(sku);
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const newCart = cart.map((item) => {
            if (item.sku === sku) {
                item.quantities = quantities;
            }
            return item;
        });
        localStorage.setItem('cart', JSON.stringify(newCart));
        loadCartCheckout();
    });
    $(document).on('click', '.js-delete-cart-item', function () {
        const sku = $(this).closest('.cart-item').find('.js-sku').text();
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const newCart = cart.filter((item) => item.sku !== sku);
        localStorage.setItem('cart', JSON.stringify(newCart));
        $(this).closest('.cart-item').remove();
        loadCartCheckout();
    });

    function loadCartCheckout() {
        let cart_items = $('.js-cart-item');
        cart_items = cart_items.filter(function (cart_item) {
            return $(this).find('.js-check-cart-item').prop('checked');
        });
        let html = '';
        let total_price = 0;
        cart_items.each(function () {
            const sku = $(this).find('.info p.js-sku').text();
            const name = $(this).find('.info p:not(.js-sku)').text();
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const cartItem = cart.find((item) => item.sku === sku) || {};
            const quantities = parseInt($(this).find('.js-cart-quantities-input').val());
            const unit_price = convertCurrencyToNumber($(this).find('.js-unit-price').text());
            html += `<div class="checkout-item" data-sku="${sku}" data-unit-price="${unit_price}" data-quantities="${quantities}">
                        <p>x<span>${quantities}</span> <span>${name}</span> ${
                cartItem.color_name ? `<small>(${cartItem.color_name})</small>` : ''
            }</p>
                        <p>${formatter.format(unit_price * quantities)}đ</p>
                    </div>`;
            total_price += unit_price * quantities;
        });
        $('.js-checkout-content').html(html);
        $('.js-cart-order-total-price').text(formatter.format(total_price) + 'đ');
        if (cart_items.length === 0) {
            $('.js-cart-checkout-btn').addClass('disable');
        } else {
            $('.js-cart-checkout-btn').removeClass('disable');
        }
    }
    $(document).on('click', '.js-check-cart-item', function () {
        loadCartCheckout();
    });

    $(document).on('click', '.js-cart-checkout-btn', function () {
        if (!$(this).hasClass('disable')) {
            let checkout_items = $('.checkout-item');
            let checkout = [];
            for (let i = 0; i < checkout_items.length; i++) {
                const item = checkout_items[i];
                const sku = $(item).data('sku');
                const name = $(item).find('span').eq(1).text();
                const unit_price = $(item).data('unit-price');
                const quantities = $(item).data('quantities');
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                const cartItem = cart.find((cartItem) => cartItem.sku === sku) || {};
                checkout.push({
                    sku,
                    name,
                    unit_price,
                    quantities,
                    color_name: cartItem.color_name,
                    color_hex: cartItem.color_hex,
                    image: cartItem.image,
                });
            }
            localStorage.setItem('checkout', JSON.stringify(checkout));
            window.location.href = '/checkout';
        }
    });
    $(document).on('click', '.js-mini-cart-checkout-btn', function () {
        if (!$(this).hasClass('disable')) {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            localStorage.setItem('checkout', JSON.stringify(cart));
            window.location.href = '/checkout';
        }
    });

    function loadCheckoutProduct() {
        const checkout = JSON.parse(localStorage.getItem('checkout')) || [];
        let total_price = 0;
        let total_quantities = 0;
        for (let i = 0; i < checkout.length; i++) {
            const item = checkout[i];
            let subtotal_price = convertCurrencyToNumber(item.unit_price + '') * parseInt(item.quantities);
            total_price += subtotal_price;
            total_quantities += parseInt(item.quantities);
            $('.js-checkout-product').append(
                `<li>x${item.quantities} ${item.name}${
                    item.color_name ? ` <small>(${item.color_name})</small>` : ''
                } <span>${formatter.format(subtotal_price)}đ</span></li>`,
            );
        }
        $('.js-checkout-total-product').text(total_quantities + ' products');
        $('.js-checkout-total-price').text(formatter.format(total_price) + 'đ');
    }
    loadCheckoutProduct();

    $(document).on('submit', '#checkout-form', function (event) {
        event.preventDefault();
        const form = $(this);
        const submitButton = form.find('.place-order-btn');

        if (submitButton.data('loading')) {
            return;
        }

        let data = form.serialize();
        const paymentMethod = $('input[name="payment_method"]:checked').val();
        const actionUrl =
            paymentMethod === 'vnpay'
                ? form.data('vnpay-action') || form.attr('action')
                : form.data('cod-action') || form.attr('action');

        let checkout = JSON.parse(localStorage.getItem('checkout')) || [];
        if (checkout.length === 0) {
            showToast('You have no product to checkout!', 'error');
            return;
        }
        checkout = checkout.map((item) => ({
            sku: item.sku,
            quantities: item.quantities,
            unit_price: convertCurrencyToNumber(item.unit_price + ''),
        }));
        data += `&checkout=${JSON.stringify(checkout)}`;
        submitButton.data('loading', true);
        submitButton.addClass('is-loading');
        submitButton.attr('disabled', true);
        submitButton.text(paymentMethod === 'vnpay' ? 'Redirecting to VNPay...' : 'Placing order...');

        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: data,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            success: function (response) {
                const redirectUrl = typeof response === 'string' ? response : response.redirect_url;

                if (!redirectUrl) {
                    showToast('Checkout succeeded but no redirect URL was returned.', 'error');
                    return;
                }

                // delete checkout storage

                let cart = JSON.parse(localStorage.getItem('cart')) || [];
                checkout = checkout.map((item) => item.sku);
                cart = cart.filter((cart_item) => !checkout.includes(cart_item.sku));
                localStorage.setItem('cart', JSON.stringify(cart));
                localStorage.removeItem('checkout');

                // redirect to checkout page
                window.location.assign(redirectUrl);
            },
            error: function (error) {
                $('#checkout-error').removeClass('d-none');
                const message = error.responseJSON?.errors
                    ? Object.values(error.responseJSON.errors)[0][0]
                    : 'Checkout failed. Please try again.';
                $('#checkout-error').text('*' + message);
                console.log(error);
            },
            complete: function () {
                submitButton.data('loading', false);
                submitButton.removeClass('is-loading');
                submitButton.attr('disabled', false);
                submitButton.text('Place order');
            },
        });
    });

    $(document).on('click', '.js-buy-now', function () {
        const quantity_input = $('.js-quantity-input');
        let quantities = parseInt(quantity_input.val());
        const sku = $('.detailed-product-tag.active').data('sku');
        const activeVariant = $('.detailed-product-tag.active');
        const unit_price = $('.js-product-name-price:not(.d-none) .js-unit-price').text();
        const name = $('.js-product-name-price:not(.d-none) .js-product-name').text();
        const image = activeVariant.data('image');

        // save to local storage
        const checkout = [
            {
                sku,
                quantities,
                unit_price,
                name,
                image,
                color_name: activeVariant.data('color-name'),
                color_hex: activeVariant.data('color-code'),
            },
        ];
        localStorage.setItem('checkout', JSON.stringify(checkout));
        window.location.href = '/checkout';
    });

    // cancel order
    $('#cancel-order-form').on('submit', function (event) {
        event.preventDefault();
        const order_id = $(this).find('#order_id').val();
        const data = $(this).serialize();
        $.ajax({
            url: `/myorders/${order_id}`,
            method: 'PATCH',
            data,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            success: function (response) {
                console.log(response);
                window.location.reload();
            },
            error: function (error) {
                console.log(error);
            },
        });
    });
});
