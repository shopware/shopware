;(function ($, Modernizr, location) {
    'use strict';

    /**
     * Ajax Product navigation
     *
     * The jQuery plugin provides the product navigation (= previous / next product and the overview button) using AJAX.
     * The plugin is necessary to fully support the HTTP cache.
     *
     * Please keep in mind that the plugin only works when the url contains the category parameter and the browser
     * needs to support {@link window.sessionStorage}.
     */
    $.plugin('swAjaxProductNavigation', {

        /**
         * Default configuration of the plugin
         *
         * @type {Object}
         */
        defaults: {

            /**
             * Animation speed in milliseconds of the arrow fading.
             *
             * @type {Number}
             */
            arrowFadeSpeed: 500,

            /**
             * Default offset of the arrows.
             *
             * @type {Number}
             */
            arrowOffset: 40,

            /**
             * Offset of the arrows in pixel when they get hovered over.
             *
             * @type {Number}
             */
            arrowSlideOffset: 140,

            /**
             * Class to enable the arrow sliding.
             *
             * @type {String}
             */
            arrowSlideClass: 'can--slide',

            /**
             * Selector for the product box in the listing.
             *
             * @type {String}
             */
            productBoxSelector: '.product--box',

            /**
             * Selector for the product details.
             * This element should have data attributes of the ordernumber and product navigation link.
             *
             * @type {String}
             */
            productDetailsSelector: '.product--details',

            /**
             * Selector for the previous button.
             *
             * @type {String}
             */
            prevLinkSelector: '.navigation--link.link--prev',

            /**
             * Selector for the next button.
             *
             * @type {String}
             */
            nextLinkSelector: '.navigation--link.link--next',

            /**
             * Selector for the breadcrumb back button.
             *
             * @type {String}
             */
            breadcrumbButtonSelector: '.content--breadcrumb .breadcrumb--button',

            /**
             * Selector for the image container.
             *
             * @type {String}
             */
            imageContainerSelector: '.image--container',

            /**
             * Selectors of product box childs in the listing.
             *
             * @type {Array}
             */
            listingSelectors: [
                '.listing .product--box .product--image',
                '.listing .product--box .product--title',
                '.listing .product--box .product--actions .action--more'
            ]
        },

        /**
         * Initializes the plugin and registers event listeners depending on
         * whether we are on the listing- or detail page.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                $el = me.$el,
                opts = me.opts,
                isListing = $el.hasClass('is--ctl-listing'),
                isDetail = $el.hasClass('is--ctl-detail'),
                params = me.parseQueryString(location.href);

            if (!(isListing || isDetail)) {
                return;
            }

            me.storage = StorageManager.getStorage('session');

            if (isListing) {
                me.registerListingEventListeners();
                return;
            }

            me.$prevButton = $el.find(opts.prevLinkSelector);
            me.$nextButton = $el.find(opts.nextLinkSelector);
            me.$backButton = $el.find(opts.breadcrumbButtonSelector);
            me.$productDetails = $el.find(opts.productDetailsSelector);

            me.categoryId = ~~(me.$productDetails.attr('data-category-id') || params && params.c);
            me.orderNumber = me.$productDetails.attr('data-main-ordernumber');
            me.productState = me.getProductState();
            if (!me.categoryId) {
                return;
            }

            // Clear the product state if the order numbers are not identical
            if (!$.isEmptyObject(me.productState) && me.productState.ordernumber !== me.orderNumber) {
                me.clearProductState();
                me.productState = {};
            }

            me.registerDetailEventListeners();
            me.getProductNavigation();
        },

        /**
         * Parses the given {@link url} parameter and extracts all query parameters. If the parameter is numeric
         * it will automatically based to a {@link Number} instead of a {@link String}.
         *
         * @private
         * @method parseQueryString
         * @param {String} url - Usually {@link window.location.href}
         * @returns {Object} All extracted URL-parameters
         */
        parseQueryString: function (url) {
            var params = {},
                urlParts = (url + '').split('?'),
                queryParts,
                part,
                key,
                value,
                p;

            if (urlParts.length < 2) {
                return params;
            }

            queryParts = urlParts[1].split('&');

            for (p in queryParts) {
                if (!queryParts.hasOwnProperty(p)) {
                    continue;
                }

                part = queryParts[p].split('=');

                key = decodeURIComponent(part[0]);
                value = decodeURIComponent(part[1] || '');

                params[key] = $.isNumeric(value) ? parseFloat(value) : value;
            }

            $.publish('plugin/swAjaxProductNavigation/onParseQueryString', [ this, url, params ]);

            return params;
        },

        /**
         * Reads the last saved product state by the key 'lastProductState'.
         *
         * @private
         * @method getProductState
         * @returns {Object} The last saved product state or an empty object.
         */
        getProductState: function () {
            var me = this,
                state = JSON.parse(me.storage.getItem('lastProductState')) || {};

            $.publish('plugin/swAjaxProductNavigation/onSetProductState', [ me, state ]);

            return state;
        },

        /**
         * Writes the given parameters into the {@link window.sessionStorage}.
         * The key 'lastProductState' will be used.
         *
         * @private
         * @method setProductState
         * @param {Object} params
         */
        setProductState: function (params) {
            var me = this;

            me.storage.setItem('lastProductState', JSON.stringify(params));

            $.publish('plugin/swAjaxProductNavigation/onSetProductState', [ me, params ]);
        },

        /**
         * Removes the product state from the {@link window.sessionStorage}.
         *
         * @private
         * @method clearProductState
         */
        clearProductState: function () {
            var me = this;

            me.storage.removeItem('lastProductState');

            $.publish('plugin/swAjaxProductNavigation/onClearProductState', [ me ]);
        },

        /**
         * Registers the event listeners for the listing page.
         *
         * @private
         * @method registerListingEventListeners
         */
        registerListingEventListeners: function () {
            var me = this,
                selectors = me.opts.listingSelectors.join(', ');

            me.$el.on(me.getEventName('click'), selectors, $.proxy(me.onClickProductInListing, me));

            $.publish('plugin/swAjaxProductNavigation/onRegisterEventsListing', [ me ]);
        },

        /**
         * Event handler method which saves the current listing state like
         * selected sorting and active page into the {@link window.sessionStorage}
         *
         * @event click
         * @param {MouseEvent} event
         */
        onClickProductInListing: function (event) {
            var me = this,
                opts = me.opts,
                $target = $(event.target),
                $parent = $target.parents(opts.productBoxSelector),
                params = me.parseQueryString(location.href);

            me.setProductState($.extend({}, params, {
                'categoryId': ~~($parent.attr('data-category-id')),
                'ordernumber': $parent.attr('data-ordernumber')
            }));

            $.publish('plugin/swAjaxProductNavigation/onClickProductInListing', [ me, event ]);
        },

        /**
         * Registers the event listeners for the detail page.
         *
         * @private
         * @method registerDetailEventListeners
         */
        registerDetailEventListeners: function () {
            var me = this;

            StateManager.on('resize', me.checkPossibleSliding, me);

            me._on(me.$prevButton, 'click', $.proxy(me.onArrowClick, me));
            me._on(me.$nextButton, 'click', $.proxy(me.onArrowClick, me));

            $.publish('plugin/swAjaxProductNavigation/onRegisterEventsDetail', [ me ]);
        },

        /**
         * @private
         * @method onArrowClick
         */
        onArrowClick: function (event) {
            var me = this,
                $target = $(event.currentTarget);

            if (!$.isEmptyObject(me.productState)) {
                me.productState.ordernumber = $target.attr('data-ordernumber');
                me.setProductState(me.productState);
            }

            $.publish('plugin/swAjaxProductNavigation/onArrowClick', [ me, event ]);
        },

        /**
         * Checks if it's possible for the arrows to slide to full extend.
         * Also checks if there's an image to display
         * If so, add the arrow slide class to the arrows.
         *
         * @private
         * @method checkPossibleSliding
         */
        checkPossibleSliding: function () {
            var me = this,
                opts = me.opts,
                offset = opts.arrowOffset,
                slideOffset = opts.arrowSlideOffset,
                $prevBtn = me.$prevButton,
                $nextBtn = me.$nextButton,
                remainingSpacePrev,
                remainingSpaceNext,
                prevBtnImage,
                nextBtnImage;

            if (!$nextBtn.length || !$prevBtn.length) {
                return false;
            }

            remainingSpacePrev = $prevBtn.offset().left + offset;
            remainingSpaceNext = $(window).width() - $nextBtn.offset().left - $nextBtn.outerWidth() + offset;

            prevBtnImage = $prevBtn
                .find(opts.imageContainerSelector)
                .css('background-image');

            nextBtnImage = $nextBtn
                .find(opts.imageContainerSelector)
                .css('background-image');

            $prevBtn[(prevBtnImage !== 'none' && remainingSpacePrev >= slideOffset) ? 'addClass' : 'removeClass'](opts.arrowSlideClass);
            $nextBtn[(nextBtnImage !== 'none' && remainingSpaceNext >= slideOffset) ? 'addClass' : 'removeClass'](opts.arrowSlideClass);

            $.publish('plugin/swAjaxProductNavigation/onCheckPossibleSliding', [ me ]);
        },

        /**
         * Requests the product navigation information from the server side
         * using an AJAX request.
         *
         * The url will be fetched from the product details element by
         * the 'data-product-navigation' attribute.
         *
         * @private
         * @method getProductNavigation
         */
        getProductNavigation: function () {
            var me = this,
                url = me.$productDetails.attr('data-product-navigation'),
                params = $.extend({}, me.productState, {
                    'ordernumber': me.orderNumber,
                    'categoryId': me.categoryId
                });

            if ($.isEmptyObject(params) || !url || !url.length) {
                return;
            }

            $.ajax({
                'url': url,
                'data': params,
                'method': 'GET',
                'dataType': 'json',
                'success': $.proxy(me.onProductNavigationLoaded, me)
            });

            $.publish('plugin/swAjaxProductNavigation/onGetProductNavigation', [ me ]);
        },

        /**
         * Sets the requested product navigation information into the DOM and displays the
         * prev and next arrow.
         *
         * @private
         * @method onProductNavigationLoaded
         * @param {Object} response - Server response
         */
        onProductNavigationLoaded: function (response) {
            var me = this,
                opts = me.opts,
                $prevBtn = me.$prevButton,
                $nextBtn = me.$nextButton,
                listing = response.currentListing,
                prevProduct = response.previousProduct,
                nextProduct = response.nextProduct,
                animSpeed = opts.arrowFadeSpeed,
                animCss = {
                    opacity: 1
                };

            $.publish('plugin/swAjaxProductNavigation/onProductNavigationLoaded', [ me, response ]);

            if (listing && listing.href) {
                me.$backButton.attr('href', listing.href);
            }

            if (typeof prevProduct === 'object') {
                $prevBtn.attr('data-ordernumber', prevProduct.orderNumber);

                if (prevProduct.image) {
                    $prevBtn
                        .find(opts.imageContainerSelector)
                        .css('background-image', 'url(' + prevProduct.image.thumbnails[0].source + ')');
                }

                $prevBtn
                    .attr('href', prevProduct.href)
                    .attr('title', prevProduct.name)
                    .css('display', 'inline');

                if (Modernizr.csstransitions) {
                    $prevBtn.transition(animCss, animSpeed);
                } else {
                    $prevBtn.animate(animCss, animSpeed);
                }
            }

            if (typeof nextProduct === 'object') {
                $nextBtn.attr('data-ordernumber', nextProduct.orderNumber);

                if (nextProduct.image) {
                    $nextBtn
                        .find(opts.imageContainerSelector)
                        .css('background-image', 'url(' + nextProduct.image.thumbnails[0].source + ')');
                }

                $nextBtn
                    .attr('href', nextProduct.href)
                    .attr('title', nextProduct.name)
                    .css('display', 'inline');

                if (Modernizr.csstransitions) {
                    $nextBtn.transition(animCss, animSpeed);
                } else {
                    $nextBtn.animate(animCss, animSpeed);
                }
            }

            me.checkPossibleSliding();

            $.publish('plugin/swAjaxProductNavigation/onProductNavigationFinished', [ me, response ]);
        },

        /**
         * Destroys the plugin by removing all listeners.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                selectors = me.opts.listingSelectors.join(', ');

            StateManager.off('resize', me.checkPossibleSliding, me);

            me.$el.off(me.getEventName('click'), selectors);

            me._destroy();
        }
    });
})(jQuery, Modernizr, location);
