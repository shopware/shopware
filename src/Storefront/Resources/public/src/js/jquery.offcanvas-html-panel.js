;(function ($) {
    /**
     * Shopware Offcanvas HTML Panel
     *
     * This plugin displays the given content inside an off canvas menu
     *
     * @example
     *
     * HTML Structure
     *
     * <div class="teaser--text-long">Off Canvas Content</div>
     * <div class="teaser--text-short is--hidden">
     *      Short Description with the
     *
     *      <a href="" class="text--offcanvas-link">Off canvas trigger element</a>
     * </div>
     *
     * <div class="teaser--text-offcanvas is--hidden">
     *      <a href="" class="close--off-canvas"><i class="icon--arrow-left"></i> Close window</a>
     * </div>
     *
     * <div class="offcanvas--content">This content will be displayed inside the off canvas menu.</div>
     *
     *
     * jQuery Initializing for all viewports
     *
     * StateManager.addPlugin('.category--teaser', 'swOffcanvasHtmlPanel');
     *
     * jQuery Initializing for some states
     *
     * StateManager.addPlugin('.category--teaser', 'swOffcanvasHtmlPanel', ['xs', 's']);
     *
     */
    $.plugin('swOffcanvasHtmlPanel', {

        defaults: {
            /**
             * Offcanvas Content which will be displayed in the off canvas menu
             *
             * @property offcanvasContent
             * @type {String}
             */
            'offcanvasContent': '.teaser--text-long',

            /**
             * Short description which will be displayed if viewport match plugin configuration
             *
             * @property shortDescription
             * @type {String}
             */
            'shortDescription': '.teaser--text-short',

            /**
             * Off canvas trigger element
             *
             * @property offcanvasTrigger
             * @type {String}
             */
            'offcanvasTrigger': '.text--offcanvas-link',

            /**
             * off canvas container
             *
             * @property offCanvasSelector
             * @type {String}
             */
            'offCanvasSelector': '.teaser--text-offcanvas',

            /**
             * off canvas close button
             *
             * @property offCanvasCloseSelector
             * @type {String}
             */
            'offCanvasCloseSelector': '.close--off-canvas',

            /**
             * off canvas direction type
             * @type {String} (fromLeft | fromRight)
             */
            'offCanvasDirection': 'fromRight',

            /**
             * hidden class for hiding long description
             *
             * @property hiddenCls
             * @type {String}
             */
            'hiddenCls': 'is--hidden'
        },

        /**
         * Initializes the plugin and register its events
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                opts = me.opts,
                $el = me.$el;

            me.applyDataAttributes();

            me._$shortText = $el.find(opts.shortDescription).removeClass(opts.hiddenCls);
            me._$longText = $el.find(opts.offcanvasContent).addClass(opts.hiddenCls);
            me._$offCanvas = $el.find(opts.offCanvasSelector).removeClass(opts.hiddenCls);
            me._$offcanvasTrigger = $el.find(opts.offcanvasTrigger);

            me._$offcanvasTrigger.swOffcanvasMenu({
                'offCanvasSelector': opts.offCanvasSelector,
                'closeButtonSelector': opts.offCanvasCloseSelector,
                'direction': opts.offCanvasDirection
            });
        },

        /**
         * This method removes all plugin specific classes
         * and removes all registered events
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                hiddenClass = me.opts.hiddenCls,
                plugin = me._$offcanvasTrigger.data('plugin_swOffcanvasMenu');

            // redesign content to old structure
            me._$longText.removeClass(hiddenClass);
            me._$shortText.addClass(hiddenClass);

            // hide offcanvas menu
            me._$offCanvas.addClass(hiddenClass);

            if (plugin) {
                plugin.destroy();
            }

            me._destroy();
        }
    });
})(jQuery);
