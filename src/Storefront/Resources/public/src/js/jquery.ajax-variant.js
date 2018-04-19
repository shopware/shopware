;(function($, window) {
    /**
     * Shopware AJAX variant
     *
     * @example
     * HTML:
     * <div data-ajax-variants-container="true"></div>
     *
     * JS:
     * $('*[data-ajax-variants-container="true"]').swAjaxVariant();
     */
    $.plugin('swAjaxVariant', {

        /**
         * Supports the browser the history api
         * @boolean
         */
        hasHistorySupport: Modernizr.history,

        /**
         * Safari specific property which prevent safari to do another request on page load.
         * @boolean
         */
        initialPopState: true,

        /**
         * Default configuration of the plugin
         * @object
         */
        defaults: {
            productDetailsSelector: '.product--detail-upper',
            configuratorFormSelector: '.configurator--form',
            orderNumberSelector: '.entry--sku .entry--content',
            historyIdentifier: 'sw-ajax-variants',
            productDetailsDescriptionSelector: '.content--description',
            footerJavascriptInlineSelector: '#footer--js-inline'
        },

        /**
         * Initializes the plugin and sets up the necessary event handler
         */
        init: function() {
            var me = this,
                ie;

            // Check if we have a variant configurator
            if (!me.$el.find('.product--configurator').length) {
                return;
            }

            me.applyDataAttributes();

            // Detecting IE version using feature detection (IE7+, browsers prior to IE7 are detected as 7)
            ie = (function () {
                if (window.ActiveXObject === undefined) return null;
                if (!document.querySelector) return 7;
                if (!document.addEventListener) return 8;
                if (!window.atob) return 9;
                /* eslint no-proto: "off" */
                if (!document.__proto__) return 10;
                return 11;
            })();

            if (ie && ie <= 9) {
                me.hasHistorySupport = false;
            }

            me.$el
                .on(me.getEventName('click'), '*[data-ajax-variants="true"]', $.proxy(me.onChange, me))
                .on(me.getEventName('change'), '*[data-ajax-select-variants="true"]', $.proxy(me.onChange, me))
                .on(me.getEventName('click'), '.reset--configuration', $.proxy(me.onChange, me));

            $(window).on('popstate', $.proxy(me.onPopState, me));

            if (me.hasHistorySupport) {
                me.publishInitialState();
            }
        },

        /**
         * Replaces the most recent history entry, when the user enters the page.
         *
         * @returns void
         */
        publishInitialState: function() {
            var me = this,
                stateObj = me._createHistoryStateObject();

            window.history.replaceState(stateObj.state, stateObj.title);
        },

        /**
         * Requests the HTML structure of the product detail page using AJAX and injects the returned
         * content into the page.
         *
         * @param {Object} values
         * @param {Boolean} pushState
         */
        requestData: function(values, pushState) {
            var me = this,
                stateObj = me._createHistoryStateObject();

            $.loadingIndicator.open({
                closeOnClick: false,
                delay: 100
            });

            $.publish('plugin/swAjaxVariant/onBeforeRequestData', [ me, values, stateObj.location ]);

            values.template = 'ajax';

            if (stateObj.params.hasOwnProperty('c')) {
                values.c = stateObj.params.c;
            }

            $.ajax({
                url: stateObj.location,
                data: values,
                method: 'GET',
                success: function(response) {
                    var $response = $($.parseHTML(response, document, true)),
                        $productDetails,
                        $productDescription,
                        ordernumber;

                    // Replace the content
                    $productDetails = $response.find(me.opts.productDetailsSelector);
                    $(me.opts.productDetailsSelector).html($productDetails.html());

                    // Replace the description box
                    $productDescription = $response.find(me.opts.productDetailsDescriptionSelector);
                    $(me.opts.productDetailsDescriptionSelector).html($productDescription.html());

                    // Get the ordernumber for the url
                    ordernumber = $.trim(me.$el.find(me.opts.orderNumberSelector).text());

                    // Update global variables
                    window.controller = window.snippets = window.themeConfig = window.lastSeenProductsConfig = window.csrfConfig = null;
                    $(me.opts.footerJavascriptInlineSelector).replaceWith($response.filter(me.opts.footerJavascriptInlineSelector));

                    StateManager.addPlugin('*[data-image-slider="true"]', 'swImageSlider', { touchControls: true })
                        .addPlugin('.product--image-zoom', 'swImageZoom', 'xl')
                        .addPlugin('*[data-image-gallery="true"]', 'swImageGallery')
                        .addPlugin('*[data-add-article="true"]', 'swAddArticle')
                        .addPlugin('*[data-modalbox="true"]', 'swModalbox');

                    // Plugin developers should subscribe to this event to update their plugins accordingly
                    $.publish('plugin/swAjaxVariant/onRequestData', [ me, response, values, stateObj.location ]);

                    if (pushState && me.hasHistorySupport) {
                        var location = stateObj.location + '?number=' + ordernumber;

                        if (stateObj.params.hasOwnProperty('c')) {
                            location += '&c=' + stateObj.params.c;
                        }

                        window.history.pushState(stateObj.state, stateObj.title, location);
                    }
                },
                complete: function() {
                    $.loadingIndicator.close();
                }
            });
        },

        /**
         * Event handler method which will be fired when the user click the back button
         * in it's browser.
         *
         * @param {EventObject} event
         * @returns {boolean}
         */
        onPopState: function(event) {
            var me = this,
                state = event.originalEvent.state;

            if (!state || !state.hasOwnProperty('type') || state.type !== 'sw-ajax-variants') {
                return;
            }

            if ($('html').hasClass('is--safari') && me.initialPopState) {
                me.initialPopState = false;
                return;
            }

            if (!state.values.length) {
                state = '';
            }

            // Prevents the scrolling to top in webkit based browsers
            if (state && state.scrollPos) {
                window.setTimeout(function() {
                    $(window).scrollTop(state.scrollPos);
                }, 10);
            }

            $.publish('plugin/swAjaxVariant/onPopState', [ me, state ]);

            me.requestData(state.values, false);
        },

        /**
         * Event handler which will fired when the user selects a variant in the storefront.
         * @param {EventObject} event
         */
        onChange: function(event) {
            var me = this,
                $target = $(event.target),
                $form = $target.parents('form'),
                values = {};

            $.each($form.serializeArray(), function(i, item) {
                if (item.name === '__csrf_token') {
                    return;
                }

                values[item.name] = item.value;
            });

            event.preventDefault();

            if (!me.hasHistorySupport) {
                $.loadingIndicator.open({
                    closeOnClick: false,
                    delay: 0
                });
                $form.submit();

                return false;
            }

            $.publish('plugin/swAjaxVariant/onChange', [ me, values, $target ]);

            me.requestData(values, true);
        },

        /**
         * Helper method which returns all available url parameters.
         * @returns {Object}
         * @private
         */
        _getUrlParams: function() {
            var search = window.location.search.substring(1),
                urlParams = search.split('&'),
                params = {};

            $.each(urlParams, function(i, param) {
                param = param.split('=');

                if (param[0].length && param[1].length && !params.hasOwnProperty(param[0])) {
                    params[decodeURIComponent(param[0])] = decodeURIComponent(param[1]);
                }
            });

            return params;
        },

        /**
         * Helper method which returns the full URL of the shop
         * @returns {string}
         * @private
         */
        _getUrl: function() {
            return window.location.protocol + '//' + window.location.host + window.location.pathname;
        },

        /**
         * Provides a state object which can be used with the {@link Window.history} API.
         *
         * The ordernumber will be fetched every time 'cause we're replacing the upper part of the detail page and
         * therefore we have to get the ordernumber using the DOM.
         *
         * @returns {Object} state object including title and location
         * @private
         */
        _createHistoryStateObject: function() {
            var me = this,
                $form = me.$el.find(me.opts.configuratorFormSelector),
                urlParams = me._getUrlParams(),
                location = me._getUrl();

            return {
                state: {
                    type: me.opts.historyIdentifier,
                    values: $form.serialize(),
                    scrollPos: $(window).scrollTop()
                },
                title: document.title,
                location: location,
                params: urlParams
            };
        }
    });
})(jQuery, window);
