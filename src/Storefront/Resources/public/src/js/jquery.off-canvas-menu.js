;(function ($) {
    'use strict';

    var $html = $('html');

    /**
     * Off canvas menu plugin
     *
     * The plugin provides an lightweight way to use an off canvas pattern for all kind of content. The content
     * needs to be positioned off canvas using CSS3 `transform`. All the rest will be handled by the plugin.
     *
     * @example Simple usage
     * ```
     *     <a href="#" data-offcanvas="true">Menu</a>
     * ```
     *
     * @example Show the menu on the right side
     * ```
     *     <a href="#" data-offcanvas="true" data-direction="fromRight">Menu</a>
     * ```
     *
     * @ToDo: Implement swipe gesture control. The old swipe gesture was removed due to a scrolling bug.
     */
    $.plugin('swOffcanvasMenu', {

        /**
         * Plugin default options.
         * Get merged automatically with the user configuration.
         */
        defaults: {

            /**
             * Selector for the content wrapper
             *
             * @property wrapSelector
             * @type {String}
             */
            'wrapSelector': '.page-wrap',

            /**
             * Whether or not the wrapper should be moved.
             *
             * @property moveWrapper
             * @type {Boolean}
             */
            'moveWrapper': false,

            /**
             * Selector of the off-canvas element
             *
             * @property offCanvasSelector
             * @type {String}
             */
            'offCanvasSelector': '.sidebar-main',

            /**
             * Selector for an additional button to close the menu
             *
             * @property closeButtonSelector
             * @type {String}
             */
            'closeButtonSelector': '.entry--close-off-canvas',

            /**
             * Animation direction, `fromLeft` (default) and `fromRight` are possible
             *
             * @property direction
             * @type {String}
             */
            'direction': 'fromLeft',

            /**
             * Additional class for the off-canvas menu for necessary styling
             *
             * @property offCanvasElementCls
             * @type {String}
             */
            'offCanvasElementCls': 'off-canvas',

            /**
             * Class which should be added when the menu will be opened on the left side
             *
             * @property leftMenuCls
             * @type {String}
             */
            'leftMenuCls': 'is--left',

            /**
             * Class which should be added when the menu will be opened on the right side
             *
             * @property rightMenuCls
             * @type {String}
             */
            'rightMenuCls': 'is--right',

            /**
             * Class which indicates if the off-canvas menu is visible
             *
             * @property activeMenuCls
             * @type {String}
             */
            'activeMenuCls': 'is--active',

            /**
             * Class which indicates if the off-canvas menu is visible
             *
             * @property openClass
             * @type {String}
             */
            'openClass': 'is--open',

            /**
             * Flag whether to show the offcanvas menu in full screen or not.
             *
             * @property fullscreen
             * @type {Boolean}
             */
            'fullscreen': false,

            /**
             * Class which sets the canvas to full screen
             *
             * @property fullscreenCls
             * @type {String}
             */
            'fullscreenCls': 'is--full-screen',

            /**
             * When this flag is set to true, the off canvas menu
             * will pop open instead of sliding.
             *
             * @property disableTransitions
             * @type {Boolean}
             */
            'disableTransitions': false,

            /**
             * The class that will be applied to the off canvas menu
             * to disable the transition property.
             *
             * @property disableTransitionCls
             * @type {String}
             */
            'disableTransitionCls': 'no--transitions',

            /**
             * The mode in which the off canvas menu should be showing.
             *
             * 'local': The given 'offCanvasSelector' will be used as the off canvas menu.
             *
             * 'ajax': The given 'offCanvasSelector' will be used as an URL to
             *         load the content via AJAX.
             *
             * @type {String}
             */
            'mode': 'local',

            /**
             * The URL that will be called when the menu is in 'ajax' mode.
             *
             * @type {String}
             */
            'ajaxURL': ''
        },

        /**
         * Initializes the plugin, sets up event listeners and adds the necessary
         * classes to get the plugin up and running.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                opts = me.opts,
                themeConfig = window.themeConfig,
                $offCanvas;

            opts.moveWrapper = opts.moveWrapper || !!(themeConfig && !~~themeConfig.offcanvasOverlayPage);

            me.applyDataAttributes();

            // Cache the necessary elements
            me.$pageWrap = $(opts.wrapSelector);

            me.isOpened = false;

            if (opts.mode === 'ajax') {
                $offCanvas = me.$offCanvas = $('<div>', {
                    'class': opts.offCanvasElementCls
                }).appendTo('body');
            } else {
                $offCanvas = me.$offCanvas = $(opts.offCanvasSelector);
                $offCanvas.addClass(opts.offCanvasElementCls);
            }

            $offCanvas.addClass((opts.direction === 'fromLeft') ? opts.leftMenuCls : opts.rightMenuCls);
            $offCanvas.addClass(opts.disableTransitionCls);

            if (!opts.disableTransitions) {
                $offCanvas.removeClass(opts.disableTransitionCls);
            }

            if (opts.fullscreen) {
                $offCanvas.addClass(opts.fullscreenCls);
            }

            // Add active class with a timeout to properly register the disable transition class.
            setTimeout(function () {
                $offCanvas.addClass(opts.activeMenuCls);
            }, 0);

            me.registerEventListeners();
        },

        /**
         * Registers all necessary event listeners for the plugin to proper operate.
         *
         * @public
         * @method onClickElement
         */
        registerEventListeners: function () {
            var me = this,
                opts = me.opts;

            // Button click
            me._on(me.$el, 'click', $.proxy(me.onClickElement, me));

            // Allow the user to close the off canvas menu
            me.$offCanvas.on(me.getEventName('click'), opts.closeButtonSelector, $.proxy(me.onClickCloseButton, me));

            $.subscribe('plugin/swOffcanvasMenu/onBeforeOpenMenu', $.proxy(me.onBeforeOpenMenu, me));

            $.publish('plugin/swOffcanvasMenu/onRegisterEvents', [ me ]);
        },

        /**
         * Called when a off canvas menu opens.
         * Closes all other off canvas menus if its not the opening menu instance.
         *
         * @param {jQuery.Event} event
         * @param {PluginBase} plugin
         */
        onBeforeOpenMenu: function (event, plugin) {
            var me = this;

            if (plugin !== me) {
                me.closeMenu();
            }
        },

        /**
         * Called when the plugin element was clicked on.
         * Opens the off canvas menu, if the clicked element is not inside
         * the off canvas menu, prevent its default behaviour.
         *
         * @public
         * @method onClickElement
         * @param {jQuery.Event} event
         */
        onClickElement: function (event) {
            var me = this;

            if (!$.contains(me.$offCanvas[0], (event.target || event.currentTarget))) {
                event.preventDefault();
            }

            me.openMenu();

            $.publish('plugin/swOffcanvasMenu/onClickElement', [ me, event ]);
        },

        /**
         * Called when the body was clicked on.
         * Closes the off canvas menu.
         *
         * @public
         * @method onClickBody
         * @param {jQuery.Event} event
         */
        onClickCloseButton: function (event) {
            var me = this;

            event.preventDefault();
            event.stopPropagation();

            me.closeMenu();

            $.publish('plugin/swOffcanvasMenu/onClickCloseButton', [ me, event ]);
        },

        /**
         * Opens the off-canvas menu based on the direction.
         * Also closes all other off-canvas menus.
         *
         * @public
         * @method openMenu
         */
        openMenu: function () {
            var me = this,
                opts = me.opts,
                menuWidth = me.$offCanvas.outerWidth();

            if (me.isOpened) {
                return;
            }
            me.isOpened = true;

            $.publish('plugin/swOffcanvasMenu/onBeforeOpenMenu', [ me ]);

            $html.addClass('no--scroll');

            $.overlay.open({
                onClose: $.proxy(me.closeMenu, me)
            });

            if (opts.moveWrapper) {
                if (opts.direction === 'fromRight') {
                    menuWidth *= -1;
                }

                me.$pageWrap.css('left', menuWidth);
            }

            me.$offCanvas.addClass(opts.openClass);

            $.publish('plugin/swOffcanvasMenu/onOpenMenu', [ me ]);

            if (opts.mode === 'ajax' && opts.ajaxURL) {
                $.ajax({
                    url: opts.ajaxURL,
                    success: function (result) {
                        me.$offCanvas.html(result);
                    }
                });
            }
        },

        /**
         * Closes the menu and slides the content wrapper
         * back to the normal position.
         *
         * @public
         * @method closeMenu
         */
        closeMenu: function () {
            var me = this,
                opts = me.opts;

            if (!me.isOpened) {
                return;
            }
            me.isOpened = false;

            $.overlay.close();

            // Disable scrolling on body
            $html.removeClass('no--scroll');

            if (opts.moveWrapper) {
                me.$pageWrap.css('left', 0);
            }

            me.$offCanvas.removeClass(opts.openClass);

            $.publish('plugin/swOffcanvasMenu/onCloseMenu', [ me ]);
        },

        /**
         * Destroys the initialized plugin completely, so all event listeners will
         * be removed and the plugin data, which is stored in-memory referenced to
         * the DOM node.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                opts = me.opts;

            me.closeMenu();

            me.$offCanvas.removeClass(opts.offCanvasElementCls)
                .removeClass(opts.activeMenuCls)
                .removeClass(opts.openClass)
                .removeAttr('style');

            if (opts.moveWrapper) {
                me.$pageWrap.removeAttr('style');
            }

            me.$el.off(me.getEventName('click'), opts.closeButtonSelector);

            $.unsubscribe('plugin/swOffcanvasMenu/onBeforeOpenMenu', $.proxy(me.onBeforeOpenMenu, me));

            me._destroy();
        }
    });
})(jQuery);
