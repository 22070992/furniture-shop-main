jQuery(document).ready(function () {
    // format number
    let formatter = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
    });
    // show toast function
    function showToast(message, type) {
        Toastify({
            className: `toastify-custom ${type}`,
            text: message,
            close: true,
            duration: 3000,
        }).showToast();
    }

    function isStateActive(key, productId) {
        const state = window.FuremirState || {};
        const ids = state[key] || [];

        return ids.map(Number).includes(Number(productId));
    }

    // helper function
    function enableOrderActionButton(enable) {
        if (enable) {
            $('.js-buy-now').attr('disabled', false);
            $('.js-add-to-cart').attr('disabled', false);
            $('.js-add-to-cart').removeClass('disable');
            $('.js-buy-now').removeClass('disable');
        } else {
            $('.js-buy-now').attr('disabled', true);
            $('.js-add-to-cart').attr('disabled', true);
            $('.js-add-to-cart').addClass('disable');
            $('.js-buy-now').addClass('disable');
        }
    }

    function addCartItem(cartItem) {
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        for (let i = 0; i < cart.length; i++) {
            if (cart[i].sku === cartItem.sku) {
                cart[i].quantities += cartItem.quantities;
                cart[i].color_name = cartItem.color_name || cart[i].color_name;
                cart[i].color_hex = cartItem.color_hex || cart[i].color_hex;
                cart[i].image = cartItem.image || cart[i].image;
                localStorage.setItem('cart', JSON.stringify(cart));
                showToast('Add to cart successfully!', 'success');
                return;
            }
        }
        cart.push(cartItem);
        $('.js-total-cart').text(cart.length);
        $('.js-total-cart').addClass('bg-black');
        localStorage.setItem('cart', JSON.stringify(cart));
        showToast('Add to cart successfully!', 'success');
    }

    $(document).on('click', '.detailed-product-tag', function (e) {
        // pre process
        if ($(this).hasClass('active') || $(this).hasClass('disable')) return;
        $('.detailed-product-tag').removeClass('active');
        $(this).addClass('active');

        const sku = $(this).data('sku');
        const image = $(this).data('image');
        const colorName = $(this).data('color-name');
        const size = $(this).data('size');
        const stock = $(this).data('stock');
        const description = $(this).data('description');

        $('.js-product-sku').text(sku);
        $('.js-selected-color-name').text(colorName);
        $('.js-product-size').text(size);
        $('.js-info-color').text(colorName);
        $('.js-info-size').text(size);
        $('.js-info-stock').text(stock);
        $('.js-product-description').text(description);
        $('.js-main-product-image').attr('src', image).attr('alt', colorName);
        $('.js-main-product-thumb').attr('src', image).attr('alt', colorName);
        $('.js-main-product-link').attr('href', image);
        $('.js-main-product-popup').attr('href', image);
        $(`.js-product-name-price`).addClass('d-none');
        $(`.js-product-name-price.${sku}`).removeClass('d-none');
        $('.js-product-quantities').addClass('d-none');
        $(`.js-product-quantities.${sku}`).removeClass('d-none');
        $('.quantity-input').val(0);
        enableOrderActionButton(false);
        // get data
    });

    $(document).on('click', '.js-quantity-add', function (e) {
        const quantity_input = $('.js-quantity-input');
        const max_value = parseInt($('.js-product-quantities:not(.d-none)').data('quantities'));
        if (quantity_input.val() === '') {
            quantity_input.val(1);
            return;
        }
        const current_value = parseInt(quantity_input.val());
        if (current_value >= max_value) return;
        quantity_input.val(current_value + 1);
        enableOrderActionButton(true);
    });
    $(document).on('click', '.js-quantity-minus', function (e) {
        const quantity_input = $('.js-quantity-input');
        if (quantity_input.val() === '') {
            quantity_input.val(0);
            enableOrderActionButton(false);
            return;
        }
        const current_value = parseInt(quantity_input.val());
        let pre_value = current_value - 1;
        if (pre_value <= 0) {
            quantity_input.val(0);
            enableOrderActionButton(false);
        } else {
            quantity_input.val(pre_value);
        }
    });

    $('.js-quantity-input').on('input', function (e) {
        const quantity_input = $('.js-quantity-input');
        const max_value = parseInt($('.js-product-quantities:not(.d-none)').data('quantities'));
        const current_value = parseInt(quantity_input.val());
        if (current_value >= max_value) {
            quantity_input.val(max_value);
        } else if (current_value <= 0) {
            quantity_input.val(0);
        }
    });

    // btn on product shop
    $(document).on('click', '.js-add-to-cart-shop', function (e) {
        let quantities = 1;
        const sku = $(this).data('sku');
        addCartItem({
            sku,
            quantities,
            color_name: $(this).data('color-name'),
            color_hex: $(this).data('color-code'),
            image: $(this).data('image'),
        });
    });

    $('.js-add-to-cart').on('click', function (e) {
        const quantity_input = $('.js-quantity-input');
        let quantities = parseInt(quantity_input.val());
        const activeVariant = $('.detailed-product-tag.active');
        const sku = activeVariant.data('sku');
        addCartItem({
            sku,
            quantities,
            color_name: activeVariant.data('color-name'),
            color_hex: activeVariant.data('color-code'),
            image: activeVariant.data('image'),
        });
    });

    // product pagination
    $(document).on('click', '.pagination-item', function (e) {
        if ($(this).hasClass('disabled')) return;

        const page = $(this).data('page');
        console.log('page', page);
        productFilter({ page });
    });

    function ProductPagination({ currentPage, lastPage }) {
        const prevBtn = `<li>
            <a class="pagination-item ${currentPage == 1 ? 'disabled' : ''}" href="#shop"
                data-page="${currentPage - 1}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M15 6l-6 6l6 6" />
                </svg>
            </a>
        </li>`;
        const nextBtn = `<li>
            <a class="pagination-item ${currentPage == lastPage ? 'disabled' : ''}" href="#shop"
                data-page="${currentPage + 1}">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24"
                    stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M9 6l6 6l-6 6" />
                </svg>
            </a>
        </li>`;

        const firstBtn = currentPage > 3 ? '<li><a class="pagination-item" data-page="1" href="#shop">1</a></li>' : '';
        const firstDot = currentPage > 4 ? '<li><a class="pagination-item disabled" href="#shop">...</a></li>' : '';

        const lastBtn =
            currentPage < lastPage - 2
                ? `<li><a class="pagination-item" href="#shop" data-page="${lastPage}">${lastPage}</a></li>`
                : '';

        const lastDot =
            currentPage < lastPage - 3 ? '<li><a class="pagination-item disabled" href="#shop">...</a></li>' : '';

        let pageElements = '';
        for (let i = currentPage - 2 >= 1 ? currentPage - 2 : 1; i <= currentPage + 2 && i <= lastPage; i++) {
            pageElements += `<li> <a class="pagination-item ${
                i == currentPage ? 'active disabled' : ''
            }" href="#shop" data-page="${i}">${i}</a></li>`;
        }
        return `${prevBtn}
            ${firstBtn}
            ${firstDot}
            ${pageElements}
            ${lastDot}
            ${lastBtn}
            ${nextBtn}`;
    }

    function createProductElement(product) {
        const productUrl = product.url || `/products/${product.product_id}`;
        return `<div class="col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="product-wrap mb-35" >
            <div class="custom-product-img product-img img-zoom mb-25">
                <a href="${productUrl}">
                    <img src="${product.detailed_product.image}" alt="" style="height: 275px">
                </a>
                <div class="product-badge badge-top badge-right badge-pink">
                ${
                    product.detailed_product.total_discount_percentage > 0
                        ? `<span>-${product.detailed_product.total_discount_percentage}%</span>`
                        : ''
                }
                </div>
                ${
                    product.total_quantities == 0
                        ? ` <div class="custom-product-badge product-badge badge-top badge-left badge-pink">
                    <span>Sold out</span>
                </div>`
                        : ''
                }
                
                <div class="product-action-wrap">
                    <button type="button"
                        class="js-wishlist-toggle product-action-btn-1 ${
                            isStateActive('wishlistProductIds', product.product_id) ? 'active' : ''
                        }"
                        data-product-id="${product.product_id}" title="Wishlist">
                        <i class="pe-7s-like"></i>
                    </button>
                    <button type="button"
                        class="js-compare-toggle product-action-btn-1 ${
                            isStateActive('compareProductIds', product.product_id) ? 'active' : ''
                        }"
                        data-product-id="${product.product_id}" title="Compare">
                        <i class="pe-7s-shuffle"></i>
                    </button>
                    <a href="${productUrl}" class="product-action-btn-1" title="View">
                        <i class="pe-7s-look"></i>
                    </a>
                </div>
                ${
                    product.total_quantities > 0
                        ? `<div class="product-action-2-wrap">
                <button data-sku="${product.detailed_product.sku}"
                        data-color-name="${product.detailed_product.color_name || ''}"
                        data-color-code="${product.detailed_product.color_code || ''}"
                        data-image="${product.detailed_product.image || ''}"
                        class="js-add-to-cart-shop product-action-btn-2" title="Add To Cart"><i
                        class="pe-7s-cart"></i> Add to cart</button>
            </div>`
                        : ''
                }
                
            </div>
            <div class="product-content">
                <h3><a
                        href="${productUrl}">${product.name}</a>
                </h3>
                <div class="product-price">
                ${
                    product.detailed_product.total_discount_percentage > 0
                        ? `<span class="old-price">
                        ${formatter.format(product.detailed_product.original_price)}đ
                        </span>`
                        : ''
                } 
                
                    <span class="new-price">
                        ${formatter.format(
                            product.detailed_product.original_price -
                                (product.detailed_product.original_price *
                                    product.detailed_product.total_discount_percentage) /
                                    100,
                        )}đ
                    </span>
                </div>
            </div>
        </div>
    </div>`;
    }

    function createProductListElement(product) {
        const productUrl = product.url || `/products/${product.product_id}`;
        const finalPrice =
            product.detailed_product.original_price -
            (product.detailed_product.original_price * product.detailed_product.total_discount_percentage) / 100;
        const description =
            product.description ||
            product.detailed_product.description ||
            'View product details for materials, color and stock information.';

        return `<div class="shop-list-wrap mb-35" data-feedback-id="shop.product-list-card.${product.product_id}"
            data-component="ProductListCard" data-product-id="${product.product_id}">
            <div class="row align-items-center">
                <div class="col-md-4 col-sm-5">
                    <div class="product-list-img">
                        <a href="${productUrl}">
                            <img src="${product.detailed_product.image}" alt="${product.name}" style="height: 240px; object-fit: cover;"
                                data-feedback-id="shop.product-list-image.${product.product_id}">
                        </a>
                        <div class="product-list-badge badge-right badge-pink">
                            ${
                                product.detailed_product.total_discount_percentage > 0
                                    ? `<span>-${product.detailed_product.total_discount_percentage}%</span>`
                                    : ''
                            }
                        </div>
                        <div class="product-list-quickview">
                            <a href="${productUrl}" class="product-action-btn-2" title="Quick View">
                                <i class="pe-7s-look"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-8 col-sm-7">
                    <div class="shop-list-content">
                        <h3><a href="${productUrl}">${product.name}</a></h3>
                        <div class="product-price mb-2" data-feedback-id="shop.product-list-price.${product.product_id}">
                            ${
                                product.detailed_product.total_discount_percentage > 0
                                    ? `<span class="old-price">${formatter.format(
                                          product.detailed_product.original_price,
                                      )}đ</span>`
                                    : ''
                            }
                            <span class="new-price">${formatter.format(finalPrice)}đ</span>
                        </div>
                        <p>${description.length > 160 ? `${description.slice(0, 157)}...` : description}</p>
                        <div class="product-list-action">
                            <a href="${productUrl}" class="product-action-btn-3" title="View Details">
                                <i class="pe-7s-look"></i>
                            </a>
                            <button type="button"
                                class="js-wishlist-toggle product-action-btn-3 ${
                                    isStateActive('wishlistProductIds', product.product_id) ? 'active' : ''
                                }"
                                data-product-id="${product.product_id}" title="Wishlist">
                                <i class="pe-7s-like"></i>
                            </button>
                            <button type="button"
                                class="js-compare-toggle product-action-btn-3 ${
                                    isStateActive('compareProductIds', product.product_id) ? 'active' : ''
                                }"
                                data-product-id="${product.product_id}" title="Compare">
                                <i class="pe-7s-shuffle"></i>
                            </button>
                            ${
                                product.total_quantities > 0
                                    ? `<button data-sku="${product.detailed_product.sku}"
                                        data-color-name="${product.detailed_product.color_name || ''}"
                                        data-color-code="${product.detailed_product.color_code || ''}"
                                        data-image="${product.detailed_product.image || ''}"
                                        data-feedback-id="shop.product-list-add-to-cart.${product.product_id}"
                                        class="js-add-to-cart-shop product-action-btn-3" title="Add To Cart">
                                        <i class="pe-7s-cart"></i>
                                    </button>`
                                    : ''
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    // filter
    function productFilter({ page = 1 }) {
        // search
        const search_text = $('#search-input').val();
        // sort
        const sorted_by = $('#sort-product').val();

        // category
        const category = $('.category-selected').data('category-id');
        // color
        const colors = $('.form-colorinput-input');
        const colorIds = [];
        for (let color of colors) {
            if (color.checked) {
                colorIds.push(color.dataset.id);
            }
        }

        if (colorIds.length === 0) {
            colorIds.push('all');
        }
        // tag
        const tags = $('.form-taginput-input');
        const tagIds = [];
        for (let tag of tags) {
            if (tag.checked) {
                tagIds.push(tag.dataset.id);
            }
        }
        if (tagIds.length === 0) {
            tagIds.push('all');
        }

        // price
        let minPrice = $('.price-input .input-min').data('value');
        let maxPrice = $('.price-input .input-max').data('value');
        history.pushState(
            null,
            null,
            `/shop?page=${page}&category=${category}&color=${colorIds.join(',')}&tag=${tagIds.join(
                ',',
            )}&search=${search_text}&price_from=${minPrice}&price_to=${maxPrice}&sorted_by=${sorted_by}`,
        );
        $.ajax({
            url: `/products?page=${page}&category=${category}&color=${colorIds.join(',')}&tag=${tagIds.join(
                ',',
            )}&search=${search_text}&price_from=${minPrice}&price_to=${maxPrice}&sorted_by=${sorted_by}`,
            type: 'GET',
            success: function (response) {
                let html = '';
                let listHtml = '';
                for (let i = 0; i < response.products.data.length; i++) {
                    let product = response.products.data[i];
                    html += createProductElement(product);
                    listHtml += createProductListElement(product);
                }

                $('#product-list').html(html);
                $('#product-list-view').html(listHtml);
                $(document).trigger('furemir:catalog-updated');
                $('#pagination').html(
                    ProductPagination({
                        currentPage: response.products.current_page,
                        lastPage: response.products.last_page,
                    }),
                );
            },
            error: function (error) {
                console.log(error);
            },
        });
    }

    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // search filter
    $('#search-input').on(
        'input',
        debounce(function () {
            productFilter({ page: 1 });
        }, 500),
    );

    // sorted
    $('#sort-product').change(function () {
        productFilter({ page: 1 });
    });
    // category filter
    $('.categories-options .option').click(function () {
        const category_id = $(this).data('category-id');
        $('.category-selected').text($(this).text());
        $('.category-selected').data('category-id', category_id);
        productFilter({ page: 1 });
    });

    // color filter
    $('.form-colorinput-input').change(function () {
        productFilter({ page: 1 });
    });

    // tag filter
    $('.form-taginput-input').change(function () {
        productFilter({ page: 1 });
    });

    // price filter

    function performAjaxCall() {
        productFilter({ page: 1 });
    }

    const debouncedAjaxCall = debounce(performAjaxCall, 1000);
    $('.range-input input').on('input', function () {
        let minVal = parseInt($('.range-input input').first().val());
        let maxVal = parseInt($('.range-input input').last().val());

        $('.price-input .input-min').val(formatter.format(minVal < maxVal ? minVal : maxVal) + 'đ');
        $('.price-input .input-min').data('value', minVal < maxVal ? minVal : maxVal);
        $('.price-input .input-max').val(formatter.format(maxVal > minVal ? maxVal : minVal) + 'đ');
        $('.price-input .input-max').data('value', maxVal > minVal ? maxVal : minVal);

        let leftPercent = (minVal / parseInt($('.range-input input').first().attr('max'))) * 100;
        let rightPercent = (maxVal / parseInt($('.range-input input').first().attr('max'))) * 100;
        const progress = $('.slider .progress');
        progress.css('left', `${leftPercent < rightPercent ? leftPercent : rightPercent}%`);
        progress.css('right', `${100 - (rightPercent > leftPercent ? rightPercent : leftPercent)}%`);

        // call ajax
        debouncedAjaxCall();
    });

    $('#cash-on-delivery').on('input', function () {
        if ($(this)[0].checked == true) $('#bank-select-option').addClass('d-none');
    });
    $('#payment-with-vnpay').on('input', function () {
        if ($(this)[0].checked == true) $('#bank-select-option').removeClass('d-none');
    });
});
