;(function ($, StateManager, window) {
    'use strict';

    var msPointerEnabled = window.navigator.msPointerEnabled,
        $body = $('body');

    /**
     * Shopware Search Plugin.
     *
     * The plugin controlling the search field behaviour in all possible states
     */
    $.plugin('swSearch', {

        defaults: {

            /**
             * Class which will be added when the drop down was triggered
             *
             * @type {String}
             */
            activeCls: 'is--active',

            /**
             * Class which will be used for generating search results
             *
             * @type {String}
             */
            searchFieldSelector: '.main-search--field',

            /**
             * Selector for the search result list.
             *
             * @type {String}
             */
            resultsSelector: '.main-search--results',

            /**
             * Selector for the link in a result entry.
             *
             * @type {String}
             */
            resultLinkSelector: '.search-result--link',

            /**
             * Selector for a single result entry.
             *
             * @type {String}
             */
            resultItemSelector: '.result--item',

            /**
             * Selector for the ajax loading indicator.
             *
             * @type {String}
             */
            loadingIndicatorSelector: '.form--ajax-loader',

            /**
             * Selector for the main header element.
             * On mobile viewport the header get an active class when the
             * search bar is opened for additional styling.
             *
             * @type {String}
             */
            headerSelector: '.header-main',

            /**
             * Gets added when the search bar is active on mobile viewport.
             * Handles additional styling.
             *
             * @type {String}
             */
            activeHeaderClass: 'is--active-searchfield',

            /**
             * Selector for the ajax loading indicator.
             *
             * @type {String}
             */
            triggerSelector: '.entry--trigger',

            /**
             * The URL used for the search request.
             * This option has to be set or an error will be thrown.
             *
             * @type {String}
             */
            requestUrl: '',

            /**
             * Flag whether or not the keyboard navigation is enabled
             *
             * @type {Boolean}
             */
            keyBoardNavigation: true,

            /**
             * Whether or not the active class is set by default
             *
             * @type {String}
             */
            activeOnStart: false,

            /**
             * Minimum amount of characters needed to trigger the search request
             *
             * @type {Number}
             */
            minLength: 3,

            /**
             * Time in milliseconds to wait after each key down event before
             * before starting the search request.
             * If a key was pressed in this time, the last request will be aborted.
             *
             * @type {Number}
             */
            searchDelay: 250,

            /**
             * The speed of all animations.
             *
             * @type {String|Number}
             */
            animationSpeed: 200,

            /**
             * The kay mapping for navigation the search results via keyboard.
             *
             * @type {Object}
             */
            keyMap: {
                'UP': 38,
                'DOWN': 40,
                'ENTER': 13
            }
        },

        /**
         * Initializes the plugin
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                $el = me.$el,
                opts = me.opts;

            me.applyDataAttributes();

            /**
             * The URL to which the search term will send via AJAX
             *
             * @public
             * @property requestURL
             * @type {String}
             */
            me.requestURL = opts.requestUrl || window.controller.ajax_search;

            if (!me.requestURL) {
                throw new Error('Parameter "requestUrl" needs to be set.');
            }

            /**
            * Converts the url to a protocol relative url, so we don't need to manually
            * check the used http protocol. See the example from paul irish to get an idea
            * how it should work:
            *    `http://www.paulirish.com/2010/the-protocol-relative-url/`
            *    `http://blog.httpwatch.com/2010/02/10/using-protocol-relative-urls-to-switch-between-http-and-https/`
            *
            * @param {String} url - the url which needs to be converted
            * @returns {String} converted string
            */
            var convertUrlToRelativeUrl = function(url) {
                url = url.replace('https:', '');
                url = url.replace('http:', '');

                return url;
            };

            me.requestURL = convertUrlToRelativeUrl(me.requestURL);

            /**
             * The search field itself.
             *
             * @public
             * @property $searchfield
             * @type {jQuery}
             */
            me.$searchField = $el.find(opts.searchFieldSelector);

            /**
             * The list in which the top results will be shown
             *
             * @public
             * @property $results
             * @type {jQuery}
             */
            me.$results = $el.find(opts.resultsSelector);

            /**
             * The loading indicator thats inside the search
             *
             * @public
             * @property $loader
             * @type {jQuery}
             */
            me.$loader = $el.find(opts.loadingIndicatorSelector);

            /**
             * The button to toggle the search field on mobile viewport
             *
             * @public
             * @property $toggleSearchBtn
             * @type {jQuery}
             */
            me.$toggleSearchBtn = $el.find(opts.triggerSelector);

            /**
             * The shop header to add a new class after opening
             *
             * @public
             * @property $mainHeader
             * @type {jQuery}
             */
            me.$mainHeader = $(opts.headerSelector);

            /**
             * The last search term that was entered in the search field.
             *
             * @public
             * @property lastSearchTerm
             * @type {String}
             */
            me.lastSearchTerm = '';

            /**
             * Timeout ID of the key up event.
             * The timeout is used to buffer fast key events.
             *
             * @public
             * @property keyupTimeout
             * @type {Number}
             */
            me.keyupTimeout = 0;

            /**
             * Indicates if the form is already submitted
             *
             * @type {boolean}
             * @private
             */
            me._isSubmitting = false;

            me.registerListeners();
        },

        /**
         * Registers all necessary events for the plugin.
         *
         * @public
         * @method registerListeners
         */
        registerListeners: function () {
            var me = this,
                opts = me.opts,
                $searchField = me.$searchField,
                $formElement = me.$searchField.closest('form');

            me._on($searchField, 'keyup', $.proxy(me.onKeyUp, me));
            me._on($searchField, 'keydown', $.proxy(me.onKeyDown, me));
            me._on(me.$toggleSearchBtn, 'click', $.proxy(me.onClickSearchEntry, me));
            me._on($formElement, 'submit', $.proxy(me.onSubmit, me));

            if (msPointerEnabled) {
                me.$results.on('click', opts.resultLinkSelector, function (event) {
                    window.location.href = $(event.currentTarget).attr('href');
                });
            }

            StateManager.registerListener({
                state: 'xs',
                enter: function () {
                    if (opts.activeOnStart) {
                        me.openMobileSearch();
                    }
                },
                exit: function () {
                    me.closeMobileSearch();
                }
            });

            $.publish('plugin/swSearch/onRegisterEvents', [ me ]);
        },

        /**
         * Event handler method which will be fired when the user presses a key when
         * focusing the field.
         *
         * @public
         * @method onKeyDown
         * @param {jQuery.Event} event
         */
        onKeyDown: function (event) {
            var me = this,
                opts = me.opts,
                keyMap = opts.keyMap,
                keyCode = event.which,
                navKeyPressed = opts.keyBoardNavigation && (keyCode === keyMap.UP || keyCode === keyMap.DOWN || keyCode === keyMap.ENTER);

            $.publish('plugin/swSearch/onKeyDown', [ me, event ]);

            if (navKeyPressed && me.$results.hasClass(opts.activeCls)) {
                me.onKeyboardNavigation(keyCode);
                event.preventDefault();
                return false;
            }

            return true;
        },

        /**
         * Will be called when a key was released on the search field.
         *
         * @public
         * @method onKeyUp
         * @param {jQuery.Event} event
         */
        onKeyUp: function (event) {
            var me = this,
                opts = me.opts,
                term = me.$searchField.val() + '',
                timeout = me.keyupTimeout;

            $.publish('plugin/swSearch/onKeyUp', [ me, event ]);

            if (timeout) {
                window.clearTimeout(timeout);
            }

            if (term.length < opts.minLength) {
                me.lastSearchTerm = '';
                me.closeResult();
                return;
            }

            if (term === me.lastSearchTerm) {
                return;
            }

            me.keyupTimeout = window.setTimeout($.proxy(me.triggerSearchRequest, me, term), opts.searchDelay);
        },

        /**
         * Blocks further submit events to throttle requests to the server
         *
         * @param event
         */
        onSubmit: function (event) {
            var me = this;

            if (me._isSubmitting) {
                event.preventDefault();
                return;
            }

            me._isSubmitting = true;
        },

        /**
         * Triggers an AJAX request with the given search term.
         *
         * @public
         * @method triggerSearchRequest
         * @param {String} searchTerm
         */
        triggerSearchRequest: function (searchTerm) {
            var me = this;

            me.$loader.fadeIn(me.opts.animationSpeed);

            me.lastSearchTerm = $.trim(searchTerm);

            $.publish('plugin/swSearch/onSearchRequest', [ me, searchTerm ]);

            if (me.lastSearchAjax) {
                me.lastSearchAjax.abort();
            }

            me.lastSearchAjax = $.ajax({
                'url': me.requestURL,
                'data': {
                    'sSearch': me.lastSearchTerm
                },
                'success': function (response) {
                    me.showResult(response);

                    $.publish('plugin/swSearch/onSearchResponse', [ me, searchTerm, response ]);
                }
            });
        },

        /**
         * Clears the result list and appends the given (AJAX) response to it.
         *
         * @public
         * @method showResult
         * @param {String} response
         */
        showResult: function (response) {
            var me = this,
                opts = me.opts;

            me.$loader.fadeOut(opts.animationSpeed);
            me.$results.empty().html(response).addClass(opts.activeCls).show();

            if (!StateManager.isCurrentState('xs')) {
                $body.on(me.getEventName('click touchstart'), $.proxy(me.onClickBody, me));
            }

            picturefill();

            $.publish('plugin/swSearch/onShowResult', [ me ]);
        },

        /**
         * Closes the result list and removes all its items.
         *
         * @public
         * @method closeResult
         */
        closeResult: function () {
            var me = this;

            me.$results.removeClass(me.opts.activeCls).hide().empty();

            $.publish('plugin/swSearch/onCloseResult', [ me ]);
        },

        /**
         * Called when the body was clicked after the search field went active.
         * Closes the search field and results.
         *
         * @public
         * @method onClickBody
         * @param {jQuery.Event} event
         */
        onClickBody: function (event) {
            var me = this,
                target = event.target,
                pluginEl = me.$el[0],
                resultsEl = me.$results[0];

            if (target === pluginEl || target === resultsEl || $.contains(pluginEl, target) || $.contains(resultsEl, target)) {
                return;
            }

            $body.off(me.getEventName('click touchstart'));

            me.closeMobileSearch();
        },

        /**
         * Adds support to navigate using the keyboard.
         *
         * @public
         * @method onKeyboardNavigation
         * @param {Number} keyCode
         */
        onKeyboardNavigation: function (keyCode) {
            var me = this,
                opts = me.opts,
                keyMap = opts.keyMap,
                $results = me.$results,
                activeClass = opts.activeCls,
                $selected = $results.find('.' + activeClass),
                $resultItems;

            $.publish('plugin/swSearch/onKeyboardNavigation', [ me, keyCode ]);

            if (keyCode === keyMap.UP || keyCode === keyMap.DOWN) {
                $resultItems = $results.find(opts.resultItemSelector);

                // First time the user hits the navigation key "DOWN"
                if (!$selected.length && keyCode == keyMap.DOWN) {
                    me.selectFirstResultItem($resultItems);
                    return;
                }

                // First time the user hits the navigation key "UP"
                if (!$selected.length && keyCode == keyMap.UP) {
                    me.selectLastResultItem($resultItems);
                    return;
                }

                $resultItems.removeClass(activeClass);
                if (me.selectResultItem(keyCode, $selected)) {
                    return;
                }
            }

            // Start on top or bottom if the user reached the end of the list
            switch (keyCode) {
            case keyMap.DOWN:
                me.selectFirstResultItem($resultItems);
                break;
            case keyMap.UP:
                me.selectLastResultItem($resultItems);
                break;
            case keyMap.ENTER:
                me.onPressEnter($selected);
                break;
            }
        },

        /**
         * onClickSearchTrigger event for displaying and hiding
         * the search field
         *
         * @public
         * @method onClickSearchEntry
         * @param event
         */
        onClickSearchEntry: function (event) {
            var me = this,
                $el = me.$el,
                opts = me.opts;

            $.publish('plugin/swSearch/onClickSearchEntry', [ me, event ]);

            if (!StateManager.isCurrentState('xs')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            $el.hasClass(opts.activeCls) ? me.closeMobileSearch() : me.openMobileSearch();
        },

        /**
         * Opens the mobile search bar and focuses it.
         *
         * @public
         * @method openMobileSearch
         */
        openMobileSearch: function () {
            var me = this,
                $el = me.$el,
                opts = me.opts,
                activeCls = opts.activeCls;

            $body.on(me.getEventName('click touchstart'), $.proxy(me.onClickBody, me));

            $el.addClass(activeCls);
            me.$toggleSearchBtn.addClass(activeCls);
            me.$mainHeader.addClass(opts.activeHeaderClass);

            me.$searchField.focus();

            $.publish('plugin/swSearch/onOpenMobileSearch', [ me ]);
        },

        /**
         * Closes the mobile search bar and removes its focus.
         *
         * @public
         * @method closeMobileSearch
         */
        closeMobileSearch: function () {
            var me = this,
                $el = me.$el,
                opts = me.opts,
                activeCls = opts.activeCls;

            $el.removeClass(activeCls);
            me.$toggleSearchBtn.removeClass(activeCls);
            me.$mainHeader.removeClass(opts.activeHeaderClass);

            me.$searchField.blur();

            $.publish('plugin/swSearch/onCloseMobileSearch', [ me ]);

            me.closeResult();
        },

        /**
         * @param {Object} resultItems
         */
        selectFirstResultItem: function (resultItems) {
            var me = this,
                opts = me.opts,
                activeClass = opts.activeCls;

            $.publish('plugin/swSearch/onSelectFirstResultItem', [ me, resultItems ]);

            resultItems.first().addClass(activeClass);
        },

        /**
         * @param {Object} resultItems
         */
        selectLastResultItem: function (resultItems) {
            var me = this,
                opts = me.opts,
                activeClass = opts.activeCls;

            $.publish('plugin/swSearch/onSelectLastResultItem', [ me, resultItems ]);

            resultItems.last().addClass(activeClass);
        },

        /**
         * Selects the next or previous result item based on the pressed navigation key.
         *
         * @param {Number} keyCode
         * @param {Object} $selected
         */
        selectResultItem: function (keyCode, $selected) {
            var me = this,
                opts = me.opts,
                keyMap = opts.keyMap,
                activeClass = opts.activeCls,
                $nextSibling;

            $.publish('plugin/swSearch/onSelectNextResultItem', [ me, keyCode ]);

            $nextSibling = $selected[(keyCode === keyMap.DOWN) ? 'next' : 'prev'](opts.resultItemSelector);
            if ($nextSibling.length) {
                $nextSibling.addClass(activeClass);
                return true;
            }
            return false;
        },

        /**
         * Redirects the user to the search result page on enter.
         *
         * @param {Object} $selected
         */
        onPressEnter: function ($selected) {
            var me = this,
                opts = me.opts;

            $.publish('plugin/swSearch/onPressEnter', [ me, $selected ]);

            if ($selected.length) {
                window.location.href = $selected.find(opts.resultLinkSelector).attr('href');
                return;
            }

            me.$parent.submit();
        },

        /**
         * Destroys the plugin and removes registered events.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this;

            me.closeMobileSearch();

            $body.off(me.getEventName('click touchstart'));

            me._destroy();
        }
    });
})(jQuery, StateManager, window);
