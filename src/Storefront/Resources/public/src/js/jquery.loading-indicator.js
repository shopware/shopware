;(function ($) {
    'use strict';

    /**
     * LoadingIndicator constructor
     *
     * @param {Object=} indicatorOptions - Configuration object, see {@link LoadingIndicator.defaults} for all available options.
     * @constructor
     */
    function LoadingIndicator(indicatorOptions) {
        indicatorOptions = indicatorOptions || {};
        this.options = $.extend({}, this.defaults, indicatorOptions);

        return this;
    }

    LoadingIndicator.prototype = {

        /**
         * The loader jQuery element.
         * Will be created when opening the indicator.
         * Contains the loading icon.
         *
         * @type {Null|jQuery}
         * @private
         */
        $loader: null,

        /**
         * The default options for the indicator.
         * When certain options were not passed, these will be used instead.
         *
         * @type {Object}
         */
        defaults: {

            /** @string - Loading indicator class for styling purpose */
            loaderCls: 'js--loading-indicator',

            /** @string - Icon class for the spinner */
            iconCls: 'icon--default',

            /** @string - Class which added to the render element while indicator activated */
            loadingCls: 'js--is-loading',

            /** @string - Delays the appearing of the loading indicator and overlay (in milliseconds), if defined.  */
            delay: 0,

            /** @string - Animation speed of the appearing of the components  */
            animationSpeed: 500,

            /** @boolean - `true` to allow the user to close the overlay with a click */
            closeOnClick: true,

            /** @boolean - Should a overlay be rendered */
            openOverlay: true,

            /** @boolean - Render element of the components */
            renderElement: 'body',

            /** @string - Theme of the overlay, default is `dark`, possible values are `dark` & `light`, new themes can be added using css styles */
            theme: 'dark'
        },

        /**
         * Opens the loading indicator from the initialization {@link Overlay}. The method returns
         * a jQuery deferred object to work with:
         *
         * ```
         * $.loadingIndicator.open().then(function);
         * ```
         *
         * @param {Function=} callback - Optional callback
         * @param {Object=} scope - Optional scope for the callback
         * @returns {jQuery.Deferred}
         */
        open: function (callback, scope) {
            var me = this,
                deferred = $.Deferred(),
                elements;

            callback = callback || $.noop;
            scope = scope || me;

            me.$loader = me._createLoader();
            $(me.options.renderElement).append(me.$loader).addClass(me.options.loadingCls);

            me._updateLoader();

            if (me.options.openOverlay !== false) {
                me.overlay = new Overlay($.extend({}, {
                    closeOnClick: me.options.closeOnClick,
                    onClose: me.close.bind(me),
                    delay: me.options.delay,
                    renderElement: me.options.renderElement,
                    theme: me.options.theme
                }));

                me.overlay.open();
            }

            elements = {
                loader: me,
                overlay: (me.options.openOverlay !== false) ? me.overlay.overlay : null
            };

            me._timeout = window.setTimeout(function () {
                me.$loader.fadeIn(me.options.animationSpeed, function () {
                    deferred.resolve(elements);
                    callback.call(scope, elements);
                    $.publish('plugin/swLoadingIndicator/onOpenFinished', [ me, elements ]);
                });
            }, me.options.delay);

            $.publish('plugin/swLoadingIndicator/onOpen', [ me, elements ]);

            return deferred;
        },

        /**
         * Closes the loader element along with the overlay pragmatically. The method returns
         * a jQuery deferred object to work with:
         *
         * ```
         * $.loadingIndicator.close().then(function);
         * ```
         *
         * @param {Function=} callback - Optional callback
         * @param {Object=} scope - Optional scope for the callback
         * @returns {jQuery.Deferred}
         */
        close: function (callback, scope) {
            var me = this,
                opts = me.options,
                deferred = $.Deferred();

            callback = callback || $.noop;
            scope = scope || me;

            // We don't have a loading indicator
            if (!me.$loader || me.$loader === null) {
                deferred.reject(new Error('Element does not contains a loading indicator.'));

                return deferred;
            }

            me.$loader.fadeOut(opts.animationSpeed || me.defaults.animationSpeed, function () {
                if (me._timeout) {
                    window.clearTimeout(me._timeout);
                }

                if (opts.openOverlay !== false) {
                    me.overlay.close().then(function() {
                        $(me.options.renderElement).removeClass(me.options.loadingCls);
                    });
                }

                me.$loader.remove();

                deferred.resolve(me);
                callback.call(scope);
                $.publish('plugin/swLoadingIndicator/onCloseFinished', [ me ]);
            });

            $.publish('plugin/swLoadingIndicator/onClose', [ me ]);

            return deferred;
        },

        /**
         * Updates the loader element.
         * If the current loader/icon classes differentiate with the passed options, they will be set.
         *
         * @private
         */
        _updateLoader: function () {
            var me = this,
                opts = me.options,
                $loader = me.$loader,
                $icon = $($loader.children()[0]);

            if (!$loader.hasClass(opts.loaderCls)) {
                $loader.removeClass('').addClass(opts.loaderCls);
            }

            if (!$icon.hasClass(opts.iconCls)) {
                $icon.removeClass('').addClass(opts.iconCls);
            }
        },

        /**
         * Creates the loader with the indicator icon in it.
         *
         * @returns {jQuery}
         * @private
         */
        _createLoader: function () {
            var me = this, loader;

            loader = $('<div>', {
                'class': me.options.loaderCls
            }).append($('<div>', {
                'class': me.options.iconCls
            }));

            return loader;
        }
    };

    /**
     * jQuery loading indicator component.
     *
     * @type {Object}
     */
    $.loadingIndicator = {

        /**
         * Opens/Shows the loading indicator along with the overlay.
         * If the loader is not available, it will be created.
         *
         * @param {Object|Function} indicatorOptions - Configuration object or callback
         * @param {Function=} callback - Optional callback
         * @param {Object=} scope - Optional scope for the callback
         * @returns {jQuery.Deferred}
         */
        open: function(indicatorOptions, callback, scope) {
            if ($.isFunction(indicatorOptions)) {
                callback = indicatorOptions;
                indicatorOptions = {};
            }

            callback = callback || $.noop;
            scope = scope || this;

            $.loadingIndicator.loader = new LoadingIndicator(indicatorOptions);
            return $.loadingIndicator.loader.open(callback, scope);
        },

        /**
         * Closes the loader element along with the overlay.
         * @param {Function=} callback - Optional callback
         * @param {Object=} scope - Optional scope for the callback
         * @returns {jQuery.Deferred}
         */
        close: function(callback, scope) {
            var deferred = $.Deferred();
            callback = callback || $.noop;
            scope = scope || this;

            if (!$.loadingIndicator.loader) {
                deferred.reject(new Error('No global loading indicator found.'));
                return deferred;
            }

            return $.loadingIndicator.loader.close(callback, scope);
        }
    };

    $.fn.extend({

        /**
         * Proxy plugin which creates a loading indicator (and optionally a overlay) over an
         * element. Ìf the `toggle` argument is `true` means you want to create the loading indicator
         * otherwise it will close and remove the loading indicator from the element.
         *
         * The method returns a jQuery promise which will be fulfilled when the loading indicator is displayed. If you
         * don't like to work with a jQuery promise you can still provide a callback method which does the same thing.
         *
         * @param {boolean} toggle - True to create a loading indicator otherwise it will close the loading indicator.
         * @param {Object|Function=} opts - Configuration object. Please refer to the {@link LoadingIndicator.defaults}
         * @param {Function=} callback - Callback method
         * @param {Object=} scope
         * @returns {jQuery.Deferred}
         */
        setLoading: function(toggle, opts, callback, scope) {
            var deferred = $.Deferred(),
                target = this,
                $target = $(target),
                elements;

            // The close method doesn't has options, therefore we have to switch up the arguments
            if ($.isFunction(opts)) {
                scope = callback;
                callback = opts;
                opts = {};
            }

            callback = callback || $.noop;
            scope = scope || target;
            opts = opts || {};

            if (toggle) {
                var loader = new LoadingIndicator($.extend({}, {
                    renderElement: target
                }, opts));

                if ($target.find('.' + loader.options.loaderCls).length) {
                    deferred.reject(new Error('Element has an loading indicator already.'));
                    return deferred;
                }

                loader.open().always(function(elements) {
                    $target.data('__loadingIndicator', elements);
                    deferred.resolve(target, elements);
                    callback.call(scope, elements);
                });

                return deferred;
            }

            elements = $target.data('__loadingIndicator');

            // The element doesn't has a loading indicator assigned to the elements in-memory data
            if (!elements || !elements.hasOwnProperty('loader')) {
                deferred.reject(new Error('Element does not contains a loading indicator.'));
                return deferred;
            }

            if (elements.overlay) {
                elements.overlay.close();
            }

            elements.loader.close().then(function() {
                deferred.resolve(target);
                callback.call(scope, target);
            });

            return deferred;
        }
    });

    // Expose overlay globally to the `window` object
    window.LoadingIndicator = LoadingIndicator;
})(jQuery);
