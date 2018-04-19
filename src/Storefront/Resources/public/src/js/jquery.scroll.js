;(function ($) {
    'use strict';

    /**
     * Shopware Scroll Plugin.
     *
     * This plugin scrolls the page or given element to a certain point when the
     * plugin element was clicked.
     */
    $.plugin('swScrollAnimate', {

        defaults: {

            /**
             * The selector of the container which should be scrolled.
             *
             * @property scrollContainerSelector
             * @type {String}
             */
            scrollContainerSelector: 'body, html',

            /**
             * The selector of the target element or the position in px where the container should be scrolled to.
             *
             * @property scrollTarget
             * @type {Number|String}
             */
            scrollTarget: 0,

            /**
             * The speed of the scroll animation in ms.
             *
             * @property animationSpeed
             * @type {Number}
             */
            animationSpeed: 500
        },

        /**
         * Initializes the plugin and register its events
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                opts = me.opts;

            me.applyDataAttributes();

            me.$container = $(opts.scrollContainerSelector);

            if (typeof opts.scrollTarget === 'string') {
                me.$targetEl = $(opts.scrollTarget);
            }

            me.registerEvents();
        },

        /**
         * This method registers the event listeners when when clicking
         * or tapping the plugin element.
         *
         * @public
         * @method registerEvents
         */
        registerEvents: function () {
            var me = this;

            me._on(me.$el, 'touchstart click', $.proxy(me.onClickElement, me));

            $.publish('plugin/swScrollAnimate/onRegisterEvents', [ me ]);
        },

        /**
         * This method will be called when the plugin element was either clicked or tapped.
         * It scrolls the target element to the given destination.
         *
         * @public
         * @method onClickElement
         */
        onClickElement: function (event) {
            event.preventDefault();

            var me = this,
                opts = me.opts;

            $.publish('plugin/swScrollAnimate/onClickElement', [ me, event ]);

            if (me.$targetEl) {
                me.scrollToElement(me.$targetEl);
                return;
            }

            me.scrollToPosition(opts.scrollTarget);
        },

        /**
         * Scrolls the target element to the vertical position of another element.
         *
         * @public
         * @method scrollToElement
         * @param {jQuery} $targetEl
         * @param {Number} offset
         */
        scrollToElement: function ($targetEl, offset) {
            var me = this;

            if (!$targetEl.length) {
                return;
            }

            $.publish('plugin/swScrollAnimate/onScrollToElement', [ me, $targetEl, offset ]);

            me.scrollToPosition($targetEl.offset().top + ~~(offset));
        },

        /**
         * Scrolls the target element to the given vertical position in pixel.
         *
         * @public
         * @method scrollToPosition
         * @param {Number} position
         */
        scrollToPosition: function (position) {
            var me = this;

            me.$container.animate({
                scrollTop: position
            }, me.opts.animationSpeed);

            $.publish('plugin/swScrollAnimate/onScrollToPosition', [ me, position ]);
        },

        /**
         * This method destroys the plugin and its registered events
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            this._destroy();
        }
    });
})(jQuery);
