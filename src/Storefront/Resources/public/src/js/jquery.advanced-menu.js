;(function ($, window) {
    'use strict';

    var $body = $('body'),
        $html = $('html'),
        isTouchIE = $html.hasClass('is--ie-touch');

    /**
     * Shopware Advanced Menu Plugin
     */
    $.plugin('advancedMenu', {
        /**
         * Default settings that will be used when the specific option was not specified.
         *
         * @type {Object}
         */
        defaults: {
            /**
             * Selector for the main navigation.
             *
             * @type {String}
             */
            'listSelector': '.navigation--list.container',

            /**
             * Selector for all navigation items that are not the home.
             *
             * @type {String}
             */
            'navigationItemSelector': '.navigation--entry:not(.is--home)',

            /**
             * Selector for the category link
             *
             * @type {String}
             */
            'navigationLinkSelector': '.navigation--link',

            /**
             * Selector to get the close arrow.
             *
             * @type {String}
             */
            'closeButtonSelector': '.button--close',

            /**
             * Selector to get all menu container.
             *
             * @type {String}
             */
            'menuContainerSelector': '.menu--container',

            /**
             * Class that will be set for the currently active menu.
             *
             * @type {String}
             */
            'menuActiveClass': 'menu--is-active',

            /**
             * Class that will be set for the current hovered nav item.
             *
             * @type {String}
             */
            'itemHoverClass': 'is--hovered',

            /**
             * Menu open on hover delay in milliseconds
             *
             * @type {Number}
             */
            'hoverDelay': 0
        },

        /**
         * @public
         * @method init
         */
        init: function () {
            var me = this;

            me.applyDataAttributes();

            /**
             * The navigation that the advanced menu should be applied to.
             * Wrapped by jQuery.
             *
             * @private
             * @property _$list
             * @type {jQuery}
             */
            me._$list = $(me.opts.listSelector);

            if (!me._$list.length) {
                return;
            }

            /**
             * Contains all list items of the navigation.
             * Wrapped by jQuery.
             *
             * @private
             * @property _$listItems
             * @type {jQuery}
             */
            me._$listItems = me._$list.find(me.opts.navigationItemSelector);

            /**
             * The arrow to close the advanced menu.
             * Wrapped by jQuery.
             *
             * @private
             * @property _$closeButton
             * @type {jQuery}
             */
            me._$closeButton = me.$el.find(me.opts.closeButtonSelector);

            /**
             * The index of the last touched navigation element.
             * Is used to support pointer events.
             *
             * @private
             * @property _targetIndex
             * @type {Number}
             */
            me._targetIndex = -1;

            // Register all needed events
            me.registerEvents();
        },

        /**
         * Registers the click / tap / mouseover events on the navigation items.
         * When one of them fires, the advanced menu will be opened.
         *
         * As long the mouse stays in the advanced menu, it stays opened.
         *
         * @public
         * @method registerEvents
         */
        registerEvents: function () {
            var me = this,
                $el;

            $.each(me._$listItems, function (i, el) {
                $el = $(el);

                if (window.PointerEvent && isTouchIE) {
                    me._on($el, 'pointerdown', $.proxy(me.onClickNavigationLink, me, i));
                } else if (window.MSPointerEvent && isTouchIE) {
                    me._on($el, 'MSPointerDown', $.proxy(me.onClickNavigationLink, me, i));
                } else {
                    me._on($el, 'touchstart', $.proxy(me.onTouchStart, me, i, $el));
                }

                me._on($el, 'mouseenter', $.proxy(me.onListItemEnter, me, i, $el));
                me._on($el, 'click', $.proxy(me.onClick, me, i, $el));
            });

            $body.on('mousemove touchstart', $.proxy(me.onMouseMove, me));

            me._on(me._$closeButton, 'click', $.proxy(me.onCloseButtonClick, me));
        },

        /**
         * Will be called when the user starts touching a navigation item.
         *
         * @param {Number} index
         * @param {jQuery} $el
         */
        onTouchStart: function (index, $el) {
            this._shouldPrevent = !$el.hasClass(this.opts.itemHoverClass);
        },

        /**
         * Called when a click event is triggered.
         * If touch is available preventing default behaviour.
         *
         * @param {Number} index
         * @param {jQuery} $el
         * @param {jQuery.Event} event
         */
        onClick: function (index, $el, event) {
            var me = this;

            if (me._shouldPrevent || !$el.hasClass(me.opts.itemHoverClass)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },

        /**
         * Fired when the navigation list items were clicked / tapped or when the mouse enters them.
         *
         * @event onMouseEnter
         * @param {Number} index
         * @param {jQuery} $el
         * @param {jQuery.Event} event
         */
        onListItemEnter: function (index, $el, event) {
            var me = this,
                opts = me.opts;

            me.setMenuIndex(index);

            me._$list.find('.' + opts.itemHoverClass).removeClass(opts.itemHoverClass);

            $el.addClass(opts.itemHoverClass);

            if (!opts.hoverDelay || me._shouldPrevent) {
                me.onMouseEnter(event);
            } else if (!me.hoverDelayTimeoutId) {
                me.hoverDelayTimeoutId = window.setTimeout(function () {
                    this.onMouseEnter(event);
                }.bind(me), opts.hoverDelay);
            }
        },

        /**
         * Will be called when the user starts touching a navigation item with on pointer based events.
         *
         * @event onClickNavigationLink
         * @param {Number} index
         */
        onClickNavigationLink: function (index) {
            var me = this;

            me._shouldPrevent = me._targetIndex !== index;

            me._targetIndex = index;
        },

        /**
         * Fired when the navigation list items were clicked / tapped or when the mouse enters them.
         *
         * @event onMouseEnter
         * @param {jQuery.Event} event
         */
        onMouseEnter: function (event) {
            event.preventDefault();

            this.openMenu();
        },

        /**
         * Fired when the mouse leaves the navigation list items or advanced menu.
         *
         * @event onMouseLeave
         * @param {jQuery.Event} event
         */
        onMouseMove: function (event) {
            var me = this,
                target = event.target,
                pluginEl = me.$el[0];

            if (pluginEl === target || $.contains(me.$el[0], target) || me._$listItems.has(target).length) {
                return;
            }

            if (me.hoverDelayTimeoutId) {
                window.clearTimeout(me.hoverDelayTimeoutId);
                delete me.hoverDelayTimeoutId;
            }

            me.closeMenu();
        },

        /**
         * Fired when the mouse leaves the navigation list items or advanced menu.
         *
         * @event onCloseButtonClick
         * @param {jQuery.Event} event
         */
        onCloseButtonClick: function (event) {
            var me = this;

            event.preventDefault();

            me.closeMenu();

            $.publish('plugin/swAdvancedMenu/onCloseWithButton', [ me ]);
        },

        /**
         * Sets the active menu index.
         * The index is ordered based on the menu containers.
         *
         * @public
         * @method setMenuIndex
         * @param index
         */
        setMenuIndex: function (index) {
            var me = this,
                menus = me.$el.find(me.opts.menuContainerSelector);

            menus.each(function (i, el) {
                $(el).toggleClass(me.opts.menuActiveClass, i === index);
            });

            $.publish('plugin/swAdvancedMenu/onSetMenuIndex', [ me, index ]);
        },

        /**
         * Opens / shows the advanced menu.
         *
         * @public
         * @method openMenu
         */
        openMenu: function () {
            var me = this;

            me.$el.show();

            $.publish('plugin/swAdvancedMenu/onOpenMenu', [ me ]);
        },

        /**
         * Closes / hides the advanced menu.
         *
         * @public
         * @method closeMenu
         */
        closeMenu: function () {
            var me = this,
                opts = me.opts;

            me._$list.find('.' + opts.itemHoverClass).removeClass(opts.itemHoverClass);

            me.$el.hide();

            me._targetIndex = -1;

            $.publish('plugin/swAdvancedMenu/onCloseMenu', [ me ]);
        }
    });
})(jQuery, window);

/**
 * Call the plugin when the shop is ready
 */
$(function () {
    $('*[data-advanced-menu="true"]').advancedMenu();
});
