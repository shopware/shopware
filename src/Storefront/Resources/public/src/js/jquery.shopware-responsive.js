(function($, window) {
    window.StateManager.init([
        {
            state: 'xs',
            enter: 0,
            exit: 29.9375   // 479px
        },
        {
            state: 's',
            enter: 30,      // 480px
            exit: 47.9375   // 767px
        },
        {
            state: 'm',
            enter: 48,      // 768px
            exit: 63.9375   // 1023px
        },
        {
            state: 'l',
            enter: 64,      // 1024px
            exit: 78.6875   // 1259px
        },
        {
            state: 'xl',
            enter: 78.75,   // 1260px
            exit: 322.5     // 5160px
        }
    ]);

    window.StateManager

        // OffCanvas menu
        .addPlugin('*[data-offcanvas="true"]', 'swOffcanvasMenu', ['xs', 's'])

        // Search field
        .addPlugin('*[data-search="true"]', 'swSearch')

        // Slide panel
        .addPlugin('.footer--column .column--headline', 'swCollapsePanel', {
            contentSiblingSelector: '.column--content'
        }, ['xs', 's'])

        // Collapse panel
        .addPlugin('#new-customer-action', 'swCollapsePanel', ['xs', 's'])

        // Image slider
        .addPlugin('*[data-image-slider="true"]', 'swImageSlider', { touchControls: true })

        // Image zoom
        .addPlugin('.product--image-zoom', 'swImageZoom', 'xl')

        // Collapse panel
        .addPlugin('.blog-filter--trigger', 'swCollapsePanel', ['xs', 's', 'm', 'l'])

        // Off canvas HTML Panel
        .addPlugin('.category--teaser .hero--text', 'swOffcanvasHtmlPanel', ['xs', 's'])

        // Default product slider
        .addPlugin('*[data-product-slider="true"]', 'swProductSlider')

        // Detail page tab menus
        .addPlugin('.product--rating-link, .link--publish-comment', 'swScrollAnimate', {
            scrollTarget: '.tab-menu--product'
        })
        .addPlugin('.tab-menu--product', 'swTabMenu', ['s', 'm', 'l', 'xl'])
        .addPlugin('.tab-menu--cross-selling', 'swTabMenu', ['m', 'l', 'xl'])
        .addPlugin('.tab-menu--product .tab--container', 'swOffcanvasButton', {
            titleSelector: '.tab--title',
            previewSelector: '.tab--preview',
            contentSelector: '.tab--content'
        }, ['xs'])
        .addPlugin('.tab-menu--cross-selling .tab--header', 'swCollapsePanel', {
            'contentSiblingSelector': '.tab--content'
        }, ['xs', 's'])
        .addPlugin('body', 'swAjaxProductNavigation')
        .addPlugin('*[data-collapse-panel="true"]', 'swCollapsePanel')
        .addPlugin('*[data-range-slider="true"]', 'swRangeSlider')
        .addPlugin('*[data-auto-submit="true"]', 'swAutoSubmit')
        .addPlugin('*[data-drop-down-menu="true"]', 'swDropdownMenu')
        .addPlugin('*[data-newsletter="true"]', 'swNewsletter')
        .addPlugin('*[data-pseudo-text="true"]', 'swPseudoText')
        .addPlugin('*[data-preloader-button="true"]', 'swPreloaderButton')
        .addPlugin('*[data-filter-type]', 'swFilterComponent')
        .addPlugin('*[data-listing-actions="true"]', 'swListingActions')
        .addPlugin('*[data-scroll="true"]', 'swScrollAnimate')
        .addPlugin('*[data-ajax-wishlist="true"]', 'swAjaxWishlist')
        .addPlugin('*[data-image-gallery="true"]', 'swImageGallery')

        // Emotion Ajax Loader
        .addPlugin('.emotion--wrapper', 'swEmotionLoader')

        .addPlugin('input[type="submit"][form], button[form]', 'swFormPolyfill')
        .addPlugin('select:not([data-no-fancy-select="true"])', 'swSelectboxReplacement')

        // Deferred loading of the captcha
        .addPlugin('div.captcha--placeholder[data-src]', 'swCaptcha')
        .addPlugin('*[data-modalbox="true"]', 'swModalbox')

        // Change the active tab to the customer reviews
        .addPlugin('.is--ctl-detail', 'swJumpToTab')
        .addPlugin('*[data-ajax-shipping-payment="true"]', 'swShippingPayment')

        // Initialize the registration plugin
        .addPlugin('div[data-register="true"]', 'swRegister')
        .addPlugin('*[data-last-seen-products="true"]', 'swLastSeenProducts', $.extend({}, window.lastSeenProductsConfig))
        .addPlugin('*[data-add-article="true"]', 'swAddArticle')
        .addPlugin('*[data-menu-scroller="true"]', 'swMenuScroller')
        .addPlugin('*[data-collapse-cart="true"]', 'swCollapseCart')
        .addPlugin('*[data-compare-ajax="true"]', 'swProductCompareAdd')
        .addPlugin('*[data-product-compare-menu="true"]', 'swProductCompareMenu')
        .addPlugin('*[data-infinite-scrolling="true"]', 'swInfiniteScrolling')
        .addPlugin('*[data-ajax-variants-container="true"]', 'swAjaxVariant')
        .addPlugin('*[data-subcategory-nav="true"]', 'swSubCategoryNav', ['xs', 's'])
        .addPlugin('*[data-panel-auto-resizer="true"]', 'swPanelAutoResizer')
        .addPlugin('*[data-address-selection="true"]', 'swAddressSelection')
        .addPlugin('*[data-address-editor="true"]', 'swAddressEditor')
        .addPlugin('*[data-cookie-permission="true"]', 'swCookiePermission')
    ;

    $(function($) {
        // Check if cookies are disabled and show notification
        if (!StorageManager.hasCookiesSupport) {
            createNoCookiesNoticeBox(window.snippets.noCookiesNotice);
        }

        // Create the no cookies notification message
        function createNoCookiesNoticeBox(message) {
            $('<div/>', { 'class': 'alert is--warning no--cookies' }).append(
                $('<div/>', {'class': 'alert--icon'}).append(
                    $('<i/>', {'class': 'icon--element icon--warning'})
                )
            ).append(
                $('<div/>', {
                    'class': 'alert--content',
                    'html': message
                }).append(
                    $('<a/>', {
                        'class': 'close--alert',
                        'html': '✕'
                    })
                    .on('click', function () {
                        $(this).closest('.no--cookies').hide();
                    })
                )
            ).appendTo('.page-wrap');
        }

        // Lightbox auto trigger
        $('*[data-lightbox="true"]').on('click.lightbox', function (event) {
            var $el = $(this),
                target = ($el.is('[data-lightbox-target]')) ? $el.attr('data-lightbox-target') : $el.attr('href');

            event.preventDefault();

            if (target.length) {
                $.lightbox.open(target);
            }
        });

        // Start up the placeholder polyfill, see ```jquery.ie-fixes.js```
        $('input, textarea').placeholder();

        $('.add-voucher--checkbox').on('change', function (event) {
            var method = (!$(this).is(':checked')) ? 'addClass' : 'removeClass';
            event.preventDefault();

            $('.add-voucher--panel')[method]('is--hidden');
        });

        $('.table--shipping-costs-trigger').on('click touchstart', function (event) {
            event.preventDefault();

            var $this = $(this),
                $next = $this.next(),
                method = ($next.hasClass('is--hidden')) ? 'removeClass' : 'addClass';

            $next[method]('is--hidden');
        });

        // Ajax cart amount display
        function cartRefresh() {
            var ajaxCartRefresh = window.controller.ajax_cart_refresh,
                $cartAmount = $('.cart--amount'),
                $cartQuantity = $('.cart--quantity');

            if (!ajaxCartRefresh.length) {
                return;
            }

            $.publish('plugin/swResponsive/onCartRefresh');

            $.ajax({
                'url': ajaxCartRefresh,
                'dataType': 'jsonp',
                'success': function (response) {
                    var cart = JSON.parse(response);

                    if (!cart.amount || !cart.quantity) {
                        return;
                    }

                    $cartAmount.html(cart.amount);
                    $cartQuantity.html(cart.quantity).removeClass('is--hidden');

                    if (cart.quantity == 0) {
                        $cartQuantity.addClass('is--hidden');
                    }

                    $.publish('plugin/swResponsive/onCartRefreshSuccess', [ cart ]);
                }
            });
        }

        $.subscribe('plugin/swAddArticle/onAddArticle', cartRefresh);
        $.subscribe('plugin/swCollapseCart/onRemoveArticleFinished', cartRefresh);

        $('.is--ctl-detail .reset--configuration').on('click', function () {
            $.loadingIndicator.open({
                closeOnClick: false
            });
        });
    });
})(jQuery, window);
