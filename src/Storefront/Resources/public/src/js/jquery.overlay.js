;(function ($) {
    'use strict';

    /**
     * Overlay constructor
     *
     * Initializes the overlay object and merges the defaults settings with the user configuration.
     *
     * @params {Object=} options - Configuration object, see {@link Overlay.defaults} for all available options.
     * @constructor
     */
    function Overlay(options) {
        options = options || {};
        this.options = $.extend({}, this.defaults, options);

        return this;
    }

    Overlay.prototype = {

        /** @boolean Indicator if the overlay is open or not. */
        isOpen: false,

        /**
         * The default options for the overlay.
         * When certain options were not passed, these will be used instead.
         *
         * @type {Object}
         */
        defaults: {
            /** @string Element selector which will be used as the element where the overlay will be rendered to. */
            renderElement: 'body',

            /** @string CSS class for the overlay element */
            overlayCls: 'js--overlay',

            /**
             * @string Css class for the render element to set relative position
             */
            relativeClass: 'js--overlay-relative',

            /** @string CSS class which indicates that the overlay is open - mainly used for styling purpose */
            openClass: 'is--open',

            /** @string CSS class which indicates that the overlay can be closed - mainly used for styling purpose */
            closableClass: 'is--closable',

            /** @boolean Shall the overlay be closed with a click on it */
            closeOnClick: true,

            /** @function Callback method which will be called when the user clicks on the overlay */
            onClick: $.noop,

            /** @function Callback method which will be called when the overlay is closed completely */
            onClose: $.noop,

            /** @string String representing the events which should trigger a close */
            events: [ 'click', 'touchstart', 'MSPointerDown' ].join('.overlay') + '.overlay',

            /** @boolean Shall the overlay be scrollable or not e.g. the page in the background would scroll */
            isScrollable: false,

            /** @string Theme of the overlay. `light` & `dark` (default) are available. New themes can easily be added using CSS / LESS */
            theme: 'dark',

            /** @number Delays the fade in effect for the certain amount of milliseconds */
            delay: 0
        },

        /**
         * Opens the overlay using the provided options from the initialization {@link Overlay}. The method returns
         * a jQuery deferred object to work with:
         *
         * ```
         * $.overlay.open().then(function);
         * ```
         *
         * @param {Function=} callback - Optional callback which will be called when the overlay is fully visible.
         * @param {Object=} scope - Optional scope for the callback method
         * @returns {jQuery.Deferred}
         */
        open: function(callback, scope) {
            var me = this,
                deferred = $.Deferred(),
                $renderElement = $(me.options.renderElement);

            me.$overlay = me._generateOverlay();

            callback = callback || $.noop;
            scope = scope || me;

            me._timeout = window.setTimeout(function() {
                window.clearTimeout(me._timeout);
                delete me._timeout;

                $renderElement.addClass(me.options.relativeClass);

                me.$overlay.appendTo($renderElement);

                // Fixes a timing issue in Chrome with delayed CSS3 translations
                window.setTimeout(function() {
                    me.$overlay.addClass(me.options.openClass);
                }, 1);

                me.isOpen = true;

                if (me.options.closeOnClick) {
                    me.$overlay.addClass(me.options.closableClass);
                }

                deferred.resolve(me, me.$overlay);
                callback.call(scope, me, me.$overlay);
            }, me.options.delay);

            me.$overlay.on(me.options.events, $.proxy(me.onOverlayClick, this, me.options));

            return deferred;
        },

        /**
         * Closes the overlay pragmatically. The method returns a jQuery deferred object to work with:
         *
         * ```
         * $.overlay.close().then(function);
         * ```
         *
         * @param {Function=} callback - Optional callback which will be fired when the overlay is fully closed.
         * @param {Object=} scope - Optional scope for the callback
         * @returns {jQuery.Deferred}
         */
        close: function(callback, scope) {
            var me = this,
                $renderElement = $(me.options.renderElement),
                deferred = $.Deferred();

            callback = callback || $.noop;
            scope = scope || me;

            if (me._timeout) {
                window.clearTimeout(me._timeout);
                delete me._timeout;
            }

            if (!me.isOpen) {
                deferred.reject(new Error('No global overlay found.'));
                return deferred;
            }
            me.isOpen = false;

            me.$overlay.removeClass(me.options.openClass + ' ' + me.options.closableClass);
            if (!$renderElement.hasClass(me.options.relativeClass)) {
                $renderElement.removeClass(me.options.relativeClass);
            }

            me.$overlay.one('transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd', function() {
                me.$overlay.off(me.options.events).removeAttr('style').remove();
                deferred.resolve(me);
                callback.call(scope);
            });

            return deferred;
        },

        /**
         * `click` handler if the user wants to let the overlay close on click. The method calls the configured
         * callback methods.
         *
         * @param {Object} options - Configuration object
         */
        onOverlayClick: function(options) {
            var me = this;

            if (options) {
                if (typeof options.onClick === 'function') {
                    options.onClick.call(me.$overlay);
                }

                if (options.closeOnClick === false) {
                    return;
                }

                if (typeof options.onClose === 'function' && options.onClose.call(me.$overlay) === false) {
                    return;
                }
            }

            me.close();
        },

        /**
         * Private method which creates the necessary DOM elements for the overlay and registers the overlay
         * to prevent scrolling if configured.
         *
         * @returns {jQuery}
         * @private
         */
        _generateOverlay: function() {
            var me = this,
                $overlay = $('<div>', {
                    'class': [
                        me.options.overlayCls, 'theme--' + me.options.theme
                    ].join(' ')
                });

            if (!me.options.isScrollable) {
                return $overlay.on('mousewheel DOMMouseScroll', function (event) {
                    event.preventDefault();
                });
            }

            return $overlay;
        }
    };

    /**
     * jQuery overlay component.
     *
     * @type {Object}
     */
    $.overlay = {

        /** @null|object Holder property for the overlay instance for the singleton */
        overlay: null,

        /**
         * Proxy method which initializes a new instance of the {@link Overlay}
         *
         * @param {Object|Function=} options - Optional configuration object or callback
         * @param {Function=} callback - Optional callback
         * @param {Object=} scope - Optional scope for the callback
         * @returns {jQuery.Deferred}
         */
        open: function (options, callback, scope) {
            if ($.isFunction(options)) {
                callback = options;
                scope = callback;
                options = {};
            }
            callback = callback || $.noop;
            options = options || {};
            scope = scope || this;

            $.overlay.overlay = new Overlay(options);
            return $.overlay.overlay.open(callback, scope);
        },

        /**
         * Proxy method which closes and removes the instance of the {@link Overlay} which are cached
         * in the {@link $.overlay.overlay} property.
         *
         * @param {Function=} callback - Optional callback which will be called when the overlay is fully closed.
         * @param {Object=} scope - Optional callback for the callback
         * @returns {jQuery.Deferred}
         */
        close: function (callback, scope) {
            var deferred = $.Deferred();
            callback = callback || $.noop;
            scope = scope || this;

            if (!$.overlay.overlay) {
                deferred.reject(new Error('No global overlay found.'));
                return deferred;
            }

            return $.overlay.overlay.close(callback, scope);
        }
    };

    // Expose overlay globally to the `window` object
    window.Overlay = Overlay;
})(jQuery);
