;(function($, window) {
    'use strict';

    /**
     * Parses the given {@link url} parameter and extracts all query parameters. If the parameter is numeric
     * it will automatically based to a {@link Number} instead of a {@link String}.
     * @private
     * @param {String} url - Usually {@link window.location.href}
     * @returns {{}} Object with all extracted parameters
     */
    var parseQueryString = function(url) {
        var qparams = {},
            parts = (url || '').split('?'),
            qparts, qpart,
            i = 0;

        if (parts.length <= 1) {
            return qparams;
        }

        qparts = parts[1].split('&');
        for (i in qparts) {
            var key, value;

            qpart = qparts[i].split('=');
            key = decodeURIComponent(qpart[0]);
            value = decodeURIComponent(qpart[1] || '');
            qparams[key] = ($.isNumeric(value) ? parseFloat(value, 10) : value);
        }

        return qparams;
    };

    $.plugin('swInfiniteScrolling', {

        defaults: {

            /** @bool enabled - enable or disable infinite scrolling plugin */
            'enabled': true,

            /** @string event - default "scroll" will be used for triggering this plugin */
            'eventName': 'scroll',

            /** @int categoryId - category id is used for generating ajax request */
            'categoryId': 0,

            /** @string pagingSelector - listing paging selector **/
            'pagingSelector': '.listing--paging',

            /** @string productBoxSelector - selector for single product boxes **/
            'productBoxSelector': '.product--box',

            /** @string defaultPerPageSelector - default per page selector which will be removed **/
            'defaultPerPageSelector': '.action--per-page',

            /** @string defaultChangeLayoutSelector - default change layout select which will be get a new margin **/
            'defaultChangeLayoutSelector': '.action--change-layout',

            /** @int threshold - after this threshold reached, auto fetching is disabled and the "load more" button is shown. */
            'threshold': 3,

            /** @string loadMoreCls - this class will be used for fetching further data by button. */
            'loadMoreCls': 'js--load-more',

            /** @string loadPreviousCls - this class  will be used for fetching previous data by button. */
            'loadPreviousCls': 'js--load-previous',

            /** @string Class will be used for load more or previous button */
            'loadBtnCls': 'btn is--primary is--icon-right',

            /** @string loadMoreSnippet - this snippet will be printed inside the load more button */
            'loadMoreSnippet': 'Weitere Artikel laden',

            /** @string loadPreviousSnippet - this snippet will be printed inside the load previous button */
            'loadPreviousSnippet': 'Vorherige Artikel laden',

            /** @string listingContainerSelector - will be used for prepending and appending load previous and load more button */
            'listingContainerSelector': '.listing--container',

            /** @string pagingBottomSelector - this class will be used for removing the bottom paging bar if infinite scrolling is enabled */
            'pagingBottomSelector': '.listing--bottom-paging',

            /** @string listingActionsWrapper - this class will be cloned and used as a actions wrapper for the load more and previous button */
            'listingActionsWrapper': 'infinite--actions',

            /** @string ajaxUrl - this string will be used as url for the ajax-call to load the articles */
            ajaxUrl: window.controller.ajax_listing || null,

            /** @string delegateConSelector - selector for delegate container, used for reload buttons */
            delegateConSelector: '.listing--wrapper'
        },

        /**
         * Default plugin initialisation function.
         * Handle all logic and events for infinite scrolling
         *
         * @public
         * @method init
         */
        init: function() {
            var me = this;

            me.$delegateContainer = $(me.opts.delegateConSelector);

            // Overwrite plugin configuration with user configuration
            me.applyDataAttributes();

            // Check if plugin is enabled
            if (!me.opts.enabled || !me.$el.is(':visible') || me.opts.ajaxUrl === null) {
                return;
            }

            // Remove paging top bar
            $(me.opts.pagingSelector).remove();

            // remove bottom paging bar
            $(me.opts.pagingBottomSelector).remove();

            // Check max pages by data attribute
            me.maxPages = me.$el.attr('data-pages');
            if (me.maxPages <= 1) {
                return;
            }

            // isLoading state for preventing double fetch same content
            me.isLoading = false;

            // isFinished state for disabling plugin if all pages rendered
            me.isFinished = false;

            // resetting fetch Count to prevent auto fetching after threshold reached
            me.fetchCount = 0;

            // previousPageIndex for loading in other direction
            me.previousPageIndex = 0;

            // Prepare top and bottom actions containers
            me.$buttonWrapperTop = $('<div>', {
                'class': me.opts.listingActionsWrapper
            });

            me.$buttonWrapperBottom = $('<div>', {
                'class': me.opts.listingActionsWrapper
            });

            // append load more button
            $(me.opts.listingContainerSelector).after(me.$buttonWrapperBottom);
            $(me.opts.listingContainerSelector).before(me.$buttonWrapperTop);

            // base url for push state and ajax fetch url
            me.baseUrl = window.location.href.split('?')[0];

            // Ajax configuration
            me.ajax = {
                'url': me.opts.ajaxUrl,
                'params': parseQueryString(window.location.href)
            };

            me.params = parseQueryString(window.location.href);
            me.upperParams = $.extend({}, me.params);
            me.historyParams = $.extend({}, me.params);

            me.urlBasicMode = false;

            // if no seo url is provided, use the url basic push mode
            if (!me.params.p) {
                me.basicModeSegments = window.location.pathname.split('/');
                me.basicModePageKey = $.inArray('sPage', me.basicModeSegments);
                me.basicModePageValue = me.basicModeSegments[me.basicModePageKey + 1];

                if (me.basicModePageValue) {
                    me.urlBasicMode = true;
                    me.params.p = me.basicModePageValue;
                    me.upperParams.p = me.basicModePageValue;
                }
            }

            // set page index to one if not assigned
            if (!me.params.p) {
                me.params.p = 1;
            }

            // set start page
            me.startPage = me.params.p;

            // holds the current listing url with all params
            me.currentPushState = '';

            // Check if there is/are previous pages
            if (me.params.p && me.params.p > 1) {
                me.showLoadPrevious();
            }

            // on scrolling event for auto fetching new pages and push state
            me._on(window, me.opts.eventName, $.proxy(me.onScrolling, me));

            // on load more button event for manually fetching further pages
            me.$delegateContainer.on(me.getEventName('click'), '.' + me.opts.loadMoreCls, $.proxy(me.onLoadMore, me));

            // on load previous button event for manually fetching previous pages
            me.$delegateContainer.on(me.getEventName('click'), '.' + me.opts.loadPreviousCls, $.proxy(me.onLoadPrevious, me));

            $.publish('plugin/swInfiniteScrolling/onRegisterEvents', [ me ]);
        },

        update: function () {
            var me = this;

            // disable infinite scrolling, because listing container is not visible
            me.opts.enabled = me.$el.is(':visible');

            $.publish('plugin/swInfiniteScrolling/onUpdate', [ me ]);
        },

        /**
         * onScrolling method
         */
        onScrolling: function() {
            var me = this;

            // stop fetch new page if is loading atm
            if (me.isLoading || !me.opts.enabled) {
                return;
            }

            // Viewport height
            var $window = $(window),
                docTop = $window.scrollTop() + $window.height(),

                // Get last element in list to get the reference point for fetching new data
                fetchPoint = me.$el.find(me.opts.productBoxSelector).last(),
                fetchPointOffset = fetchPoint.offset().top,
                bufferSize = fetchPoint.height(),
                triggerPoint = fetchPointOffset - bufferSize;

            if (docTop > triggerPoint && (me.params.p < me.maxPages)) {
                me.fetchNewPage();
            }

            // collect all pages
            var $products = $('*[data-page-index]'),
                visibleProducts = $.grep($products, function(item) {
                    return $(item).offset().top <= docTop;
                });

            // First visible Product
            var $firstProduct = $(visibleProducts).last(),
                tmpPageIndex = $firstProduct.attr('data-page-index');

            // Collection variables and build push state url
            var tmpParams = me.historyParams;

            // remove category id from history url
            delete tmpParams.c;

            // setting actual page index
            if (!tmpParams.p || !tmpPageIndex) {
                tmpParams.p = me.startPage;
            }

            if (tmpPageIndex) {
                tmpParams.p = tmpPageIndex;
            }

            var tmpPushState = me.baseUrl + '?' + $.param(tmpParams);

            if (me.urlBasicMode) {
                // use start page parameter if no one exists
                if (!tmpPageIndex) {
                    tmpPageIndex = me.basicModePageValue;
                }

                // redesign push url,
                var segments = me.basicModeSegments;
                segments[me.basicModePageKey + 1] = tmpPageIndex;

                tmpPushState = segments.join('/');
            }

            if (me.currentPushState != tmpPushState) {
                me.currentPushState = tmpPushState;
                if (!history || !history.pushState) {
                    return;
                }

                history.pushState('data', '', me.currentPushState);
            }

            $.publish('plugin/swInfiniteScrolling/onScrolling', [ me ]);
        },

        /**
         * fetchNewPage method
         */
        fetchNewPage: function() {
            var me = this;

            // Quit here if all pages rendered
            if (me.isFinished || me.params.p >= me.maxPages) {
                return;
            }

            // stop if process is running
            if (me.isLoading) {
                return;
            }

            // Stop automatic fetch if page threshold reached
            if (me.fetchCount >= me.opts.threshold) {
                var button = me.generateButton('next');

                // append load more button
                me.$buttonWrapperBottom.html(button);

                // set finished flag
                me.isFinished = true;

                return;
            }

            me.isLoading = true;

            me.openLoadingIndicator();

            // increase page index for further page loading
            me.params.p++;

            // increase fetch count for preventing auto fetching
            me.fetchCount++;

            // use categoryId by settings if not defined by filters
            if (!me.params.c && me.opts.categoryId) {
                me.params.c = me.opts.categoryId;
            }

            $.publish('plugin/swInfiniteScrolling/onBeforeFetchNewPage', [ me ]);

            $.publish(
                'action/fetchListing',
                [me.params, false, true, $.proxy(me.appendListing, me)]
            );

            $.publish('plugin/swInfiniteScrolling/onFetchNewPage', [ me ]);
        },

        generateButton: function(buttonType) {
            var me = this,
                type = buttonType || 'next',
                cls = (type == 'previous') ? me.opts.loadPreviousCls : me.opts.loadMoreCls,
                snippet = (type == 'previous') ? me.opts.loadPreviousSnippet : me.opts.loadMoreSnippet,
                $button = $('<a>', {
                    'class': me.opts.loadBtnCls + ' ' + cls,
                    'html': snippet + ' <i class="icon--cw is--large"></i>'
                });

            $.publish('plugin/swInfiniteScrolling/onLoadMore', [ me, $button, buttonType ]);

            return $button;
        },

        /**
         * onLoadMore method
         *
         * @param event
         */
        onLoadMore: function(event) {
            event.preventDefault();

            var me = this;

            // Remove load more button
            $('.' + me.opts.loadMoreCls).remove();

            // Set finished to false to re-enable the fetch method
            me.isFinished = false;

            // Increase threshold for auto fetch next page if there is a next page
            if (me.maxPages >= me.opts.threshold) {
                me.opts.threshold++;
            }

            // fetching new page
            me.fetchNewPage();

            $.publish('plugin/swInfiniteScrolling/onLoadMore', [ me, event ]);
        },

        /**
         * showLoadPrevious method
         *
         * Shows the load previous button
         */
        showLoadPrevious: function() {
            var me = this,
                button = me.generateButton('previous');

            // append load previous button
            me.$buttonWrapperTop.html(button);

            $.publish('plugin/swInfiniteScrolling/onShowLoadPrevious', [ me, button ]);
        },

        /**
         * onLoadPrevious method
         *
         * @param event
         *
         * will be triggered by load previous button
         */
        onLoadPrevious: function(event) {
            event.preventDefault();

            var me = this, callback;

            // Remove load previous button
            $('.' + me.opts.loadPreviousCls).remove();

            // fetching new page
            me.openLoadingIndicator('top');

            // build ajax url
            var tmpParams = me.upperParams;

            // use categoryId by settings if not defined by filters
            if (!tmpParams.c && me.opts.categoryId) {
                tmpParams.c = me.opts.categoryId;
            }

            tmpParams.p = tmpParams.p - 1;

            $.publish('plugin/swInfiniteScrolling/onBeforeFetchPreviousPage', [ me ]);

            me.previousLoadPage = tmpParams.p;

            callback = function(response) {
                me.prependListing(response);

                // Set load previous button if we aren't already on page one
                if (tmpParams.p > 1) {
                    me.showLoadPrevious();
                }
            };

            $.publish(
                'action/fetchListing',
                [tmpParams, false, true, callback]
            );

            $.publish('plugin/swInfiniteScrolling/onLoadPrevious', [ me, event ]);
        },

        /**
         * @param {object} response
         */
        appendListing: function(response) {
            var me = this, template;

            template = response.listing.trim();

            $.publish('plugin/swInfiniteScrolling/onFetchNewPageLoaded', [ me, template ]);

            // Cancel is no data provided
            if (!template) {
                me.isFinished = true;
                me.closeLoadingIndicator();
                return;
            }

            // append fetched data into listing
            me.$el.append(template);

            // trigger picturefill for regenerating thumbnail sizes
            picturefill();

            me.closeLoadingIndicator();

            // enable loading for further pages
            me.isLoading = false;

            // check if last page reached
            if (me.params.p >= me.maxPages) {
                me.isFinished = true;
            }

            $.publish('plugin/swInfiniteScrolling/onFetchNewPageFinished', [ me, template ]);
        },

        /**
         * @param {object} response
         */
        prependListing: function(response) {
            var me = this;

            // append fetched data into listing
            me.$el.prepend(response.listing.trim());

            picturefill();

            me.closeLoadingIndicator();

            // enable loading for further pages
            me.isLoading = false;

            $.publish('plugin/swInfiniteScrolling/onLoadPreviousFinished', [ me, event, response.listing ]);
        },

        /**
         * openLoadingIndicator method
         *
         * opens the loading indicator relative
         */
        openLoadingIndicator: function(type) {
            var me = this,
                $indicator = $('.js--loading-indicator.indicator--relative');

            if ($indicator.length) {
                return;
            }

            $indicator = $('<div>', {
                'class': 'js--loading-indicator indicator--relative',
                'html': $('<i>', {
                    'class': 'icon--default'
                })
            });

            if (!type) {
                me.$el.parent().after($indicator);
            } else {
                me.$el.parent().before($indicator);
            }

            $.publish('plugin/swInfiniteScrolling/onOpenLoadingIndicator', [ me, $indicator ]);
        },

        /**
         * closeLoadingIndicator method
         *
         * close the relative loading indicator
         */
        closeLoadingIndicator: function() {
            var me = this,
                $indicator = $('.js--loading-indicator.indicator--relative');

            if (!$indicator.length) {
                return;
            }

            $indicator.remove();

            $.publish('plugin/swInfiniteScrolling/onCloseLoadingIndicator', [ me ]);
        },

        /**
         * Destroys the plugin
         *
         * @public
         * @method destroy
         */
        destroy: function() {
            var me = this;

            if (me.$buttonWrapperTop) {
                me.$buttonWrapperTop.remove();
            }

            if (me.$buttonWrapperBottom) {
                me.$buttonWrapperBottom.remove();
            }

            // on load more button event for manually fetching further pages
            me.$delegateContainer.off(me.getEventName('click'), '.' + me.opts.loadMoreCls);

            // on load previous button event for manually fetching previous pages
            me.$delegateContainer.off(me.getEventName('click'), '.' + me.opts.loadPreviousCls);

            me._destroy();
        }
    });
})(jQuery, window);
