;(function ($, window) {
    'use strict';

    /**
     * Image Gallery Plugin.
     *
     * This plugin opens a clone of an existing image slider in a lightbox.
     * This image slider clone provides three control buttons (zoom in, zoom out
     * and reset zoom) and also enables advanced features of the
     * image slider plugin like pinch-to-zoom, double-tap, moving scaled images.
     */
    $.plugin('swImageGallery', {

        defaults: {

            /**
             * Selector for the image container..
             *
             * @property imageContainerSelector
             * @type {String}
             */
            imageContainerSelector: '.image-slider--container',

            /**
             * Selector for the image slider itself..
             *
             * @property imageSlideSelector
             * @type {String}
             */
            imageSlideSelector: '.image-slider--slide',

            /**
             * Selector for the thumbnail container.
             *
             * @property thumbnailContainerSelector
             * @type {String}
             */
            thumbnailContainerSelector: '.image-slider--thumbnails',

            /**
             * Class that is used for the lightbox template.
             *
             * @property imageGalleryClass
             * @type {String}
             */
            imageGalleryClass: 'image--gallery',

            /**
             * Key code for the button that let the image slider
             * slide to the previous image.
             *
             * @property previousKeyCode
             * @type {Number}
             */
            previousKeyCode: 37,

            /**
             * Key code for the button that let the image slider
             * slide to the next image.
             *
             * @property nextKeyCode
             * @type {Number}
             */
            nextKeyCode: 39,

            /**
             * Maximum zoom factor for the image slider.
             * Will be passed to the image slider configuration in the lightbox.
             *
             * @property maxZoom
             * @type {Number|String}
             */
            maxZoom: 'auto',

            /**
             * Class that will be appended to the buttons when they
             * should be disabled.
             *
             * @property disabledClass
             * @type {String}
             */
            disabledClass: 'is--disabled',

            /**
             * Base class that will be applied to every gallery button.
             *
             * @property btnClass
             * @type {String}
             */
            btnClass: 'btn is--small',

            /**
             * Class that will be applied to the zoom in button.
             *
             * @property zoomInClass
             * @type {String}
             */
            zoomInClass: 'icon--plus3 button--zoom-in',

            /**
             * Class that will be applied to the zoom out button.
             *
             * @property zoomOutClass
             * @type {String}
             */
            zoomOutClass: 'icon--minus3 button--zoom-out',

            /**
             * Class that will be applied to the reset zoom button.
             *
             * @property zoomResetClass
             * @type {String}
             */
            zoomResetClass: 'icon--resize-shrink button--zoom-reset'
        },

        /**
         * Method for the plugin initialisation.
         * Merges the passed options with the data attribute configurations.
         * Creates and references all needed elements and properties.
         * Calls the registerEvents method afterwards.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this;

            me.applyDataAttributes();

            /**
             * Reference of the image container that should be cloned.
             *
             * @private
             * @property _$imageContainer
             * @type {jQuery}
             */
            me._$imageContainer = me.$el.find(me.opts.imageContainerSelector);

            if (!me._$imageContainer.length) {
                return;
            }

            /**
             * Reference of the thumbnail container that should be cloned.
             *
             * @private
             * @property _$thumbContainer
             * @type {jQuery}
             */
            me._$thumbContainer = me.$el.find(me.opts.thumbnailContainerSelector);

            /**
             * Clone of the given image container.
             * This clone will be used in the image gallery template.
             *
             * @private
             * @property _$imageContainerClone
             * @type {jQuery}
             */
            me._$imageContainerClone = me._$imageContainer.clone();

            /**
             * Clone of the given thumbnail container.
             * This clone will be used in the image gallery template.
             *
             * @private
             * @property _$thumbContainerClone
             * @type {jQuery}
             */
            me._$thumbContainerClone = me._$thumbContainer.clone();

            /**
             * Buttons that zooms the current image out by the factor of 1.
             *
             * @public
             * @property $zoomOutBtn
             * @type {jQuery}
             */
            me.$zoomOutBtn = me.createZoomOutButton().appendTo(me._$imageContainerClone);

            /**
             * Buttons that resets the current image zoom..
             *
             * @public
             * @property $zoomResetBtn
             * @type {jQuery}
             */
            me.$zoomResetBtn = me.createZoomResetButton().appendTo(me._$imageContainerClone);

            /**
             * Buttons that zooms the current image in by the factor of 1.
             *
             * @public
             * @property $zoomInBtn
             * @type {jQuery}
             */
            me.$zoomInBtn = me.createZoomInButton().appendTo(me._$imageContainerClone);

            /**
             * Image gallery template that will be used in the modal box.
             * Will be lazy created only when its needed (on this.$el click).
             *
             * @public
             * @property $template
             * @type {jQuery|null}
             */
            me.$template = null;

            me.registerEvents();
        },

        /**
         * Creates and returns the zoom in ( [+] ) button.
         *
         * @private
         * @method createZoomInButton
         */
        createZoomInButton: function () {
            var me = this,
                opts = this.opts,
                $zoomInButton = $('<div>', {
                    'class': opts.btnClass + ' ' + opts.zoomInClass
                });

            $.publish('plugin/swImageGallery/onCreateZoomInButton', [ me, $zoomInButton ]);

            return $zoomInButton;
        },

        /**
         * Creates and returns the zoom out ( [-] ) button.
         *
         * @private
         * @method createZoomOutButton
         */
        createZoomOutButton: function () {
            var me = this,
                opts = me.opts,
                $zoomOutButton = $('<div>', {
                    'class': opts.btnClass + ' ' + opts.zoomOutClass
                });

            $.publish('plugin/swImageGallery/onCreateZoomOutButton', [ me, $zoomOutButton ]);

            return $zoomOutButton;
        },

        /**
         * Creates and returns the zoom reset ( [-><-] ) button.
         *
         * @private
         * @method createZoomResetButton
         */
        createZoomResetButton: function () {
            var me = this,
                opts = me.opts,
                $zoomResetButton = $('<div>', {
                    'class': opts.btnClass + ' ' + opts.zoomResetClass
                });

            $.publish('plugin/swImageGallery/onCreateZoomResetButton', [ me, $zoomResetButton ]);

            return $zoomResetButton;
        },

        /**
         * Registers all needed events of the plugin.
         *
         * @private
         * @method registerEvents
         */
        registerEvents: function () {
            var me = this;

            me._on(me._$imageContainer.find(me.opts.imageSlideSelector), 'click', $.proxy(me.onClick, me));

            $.subscribe('plugin/swImageSlider/onSlide', $.proxy(me.onImageUpdate, me));
            $.subscribe('plugin/swImageSlider/onUpdateTransform', $.proxy(me.onImageUpdate, me));

            me._on(window, 'keydown', $.proxy(me.onKeyDown, me));

            $.publish('plugin/swImageGallery/onRegisterEvents', [ me ]);
        },

        /**
         * Returns the image slider plugin instance of the gallery.
         * If its not available, returns null instead.
         *
         * @public
         * @method getImageSlider
         * @returns {$.PluginBase|null}
         */
        getImageSlider: function () {
            var me = this,
                $template = me.$template,
                slider = ($template && $template.data('plugin_swImageSlider')) || null;

            $.publish('plugin/swImageGallery/onGetImageSlider', [ me, slider ]);

            return slider;
        },

        /**
         * Will be called when an image or its transformation
         * in the slider was updated.
         * Toggles the buttons specific to the image slider zoom options.
         *
         * @event onImageUpdate
         * @param {jQuery.Event} event
         * @param {$.PluginBase} context
         */
        onImageUpdate: function (event, context) {
            var me = this,
                plugin = me.getImageSlider();

            if (plugin !== context) {
                return;
            }

            me.toggleButtons(plugin);

            $.publish('plugin/swImageGallery/onImageUpdate', [ me, event, plugin ]);
        },

        /**
         * Will be called when the zoom reset button was clicked.
         * Resets the current image scaling of the image slider.
         *
         * @event onResetZoom
         * @param {jQuery.Event} event
         */
        onResetZoom: function (event) {
            var me = this,
                plugin = me.getImageSlider();

            event.preventDefault();

            if (!plugin || me.$zoomResetBtn.hasClass(me.opts.disabledClass)) {
                return;
            }

            me.disableButtons();

            plugin.resetTransformation(true, function () {
                me.toggleButtons(plugin);

                $.publish('plugin/swImageGallery/onResetZoomFinished', [ me, event, plugin ]);
            });

            $.publish('plugin/swImageGallery/onResetZoom', [ me, event, plugin ]);
        },

        /**
         * Will be called when the zoom in button was clicked.
         * Zooms the image slider in by the factor of 1.
         *
         * @event onZoomIn
         * @param {jQuery.Event} event
         */
        onZoomIn: function (event) {
            var me = this,
                plugin = me.getImageSlider();

            event.preventDefault();

            if (!plugin || me.$zoomInBtn.hasClass(me.opts.disabledClass)) {
                return;
            }

            me.disableButtons();

            plugin.scale(1, true, function () {
                me.toggleButtons(plugin);

                $.publish('plugin/swImageGallery/onZoomInFinished', [ me, event, plugin ]);
            });

            $.publish('plugin/swImageGallery/onZoomIn', [ me, event, plugin ]);
        },

        /**
         * Will be called when the zoom out button was clicked.
         * Zooms the image slider out by the factor of 1.
         *
         * @event onZoomOut
         * @param {jQuery.Event} event
         */
        onZoomOut: function (event) {
            var me = this,
                plugin = me.getImageSlider();

            event.preventDefault();

            if (!plugin || me.$zoomOutBtn.hasClass(me.opts.disabledClass)) {
                return;
            }

            me.disableButtons();

            plugin.scale(-1, true, function () {
                me.toggleButtons(plugin);

                $.publish('plugin/swImageGallery/onZoomOutFinished', [ me, event, plugin ]);
            });

            $.publish('plugin/swImageGallery/onZoomOut', [ me, event, plugin ]);
        },

        /**
         * Will be called when an keyboard key was pressed.
         * If the previous/next keycode was pressed, it will slide to
         * the previous/next image.
         *
         * @event onKeyDown
         * @param {jQuery.Event} event
         */
        onKeyDown: function (event) {
            var me = this,
                opts = me.opts,
                plugin = me.getImageSlider(),
                keyCode = event.which;

            if (!plugin) {
                return;
            }

            if (keyCode === opts.previousKeyCode) {
                plugin.slidePrev();
            }

            if (keyCode === opts.nextKeyCode) {
                plugin.slideNext();
            }

            $.publish('plugin/swImageGallery/onKeyDown', [ me, event, keyCode ]);
        },

        /**
         * Creates and returns the gallery template.
         * Will be used to lazy create the slider template
         * with all its large images.
         *
         * @private
         * @method createTemplate
         * @returns {jQuery}
         */
        createTemplate: function () {
            var me = this,
                $template,
                $el,
                img;

            me._$imageContainerClone.find('span[data-img-original]').each(function (i, el) {
                $el = $(el);

                img = $('<img>', {
                    'class': 'image--element',
                    'src': $el.attr('data-img-original')
                });

                $el.replaceWith(img);
            });

            me._$thumbContainerClone.find('a.thumbnails--arrow').remove();
            me._$imageContainerClone.find('.arrow').remove();

            $template = $('<div>', {
                'class': me.opts.imageGalleryClass,
                'html': [
                    me._$imageContainerClone,
                    me._$thumbContainerClone
                ]
            });

            $.publish('plugin/swImageGallery/onCreateTemplate', [ me, $template ]);

            return $template;
        },

        /**
         * Will be called when the detail page image slider was clicked..
         * Opens the lightbox with an image slider clone in it.
         *
         * @event onClick
         */
        onClick: function (event) {
            var me = this,
                imageSlider = me.$el.data('plugin_swImageSlider');

            $.modal.open(me.$template || (me.$template = me.createTemplate()), {
                width: '100%',
                height: '100%',
                animationSpeed: 350,
                additionalClass: 'image-gallery--modal no--border-radius',
                onClose: me.onCloseModal.bind(me)
            });

            me._on(me.$zoomInBtn, 'click touchstart', $.proxy(me.onZoomIn, me));
            me._on(me.$zoomOutBtn, 'click touchstart', $.proxy(me.onZoomOut, me));
            me._on(me.$zoomResetBtn, 'click touchstart', $.proxy(me.onResetZoom, me));

            picturefill();

            me.$template.swImageSlider({
                dotNavigation: false,
                swipeToSlide: true,
                pinchToZoom: true,
                doubleTap: true,
                maxZoom: me.opts.maxZoom,
                startIndex: imageSlider ? imageSlider.getIndex() : 0,
                preventScrolling: true
            });

            me.toggleButtons(me.getImageSlider());

            $.publish('plugin/swImageGallery/onClick', [ me, event ]);
        },

        /**
         * Will be called when the modal box was closed.
         * Destroys the imageSlider plugin instance of the lightbox template.
         *
         * @event onCloseModal
         */
        onCloseModal: function () {
            var me = this,
                plugin = me.getImageSlider();

            if (!plugin) {
                return;
            }

            plugin.destroy();

            $.publish('plugin/swImageGallery/onCloseModal', [ me ]);
        },

        /**
         * This function disables all three control buttons.
         * Will be called when an animation begins.
         *
         * @public
         * @method disableButtons
         */
        disableButtons: function () {
            var me = this,
                disabledClass = me.opts.disabledClass;

            me.$zoomResetBtn.addClass(disabledClass);
            me.$zoomOutBtn.addClass(disabledClass);
            me.$zoomInBtn.addClass(disabledClass);

            $.publish('plugin/swImageGallery/onDisableButtons', [ me ]);
        },

        /**
         * This function disables all three control buttons.
         * Will be called when an animation begins.
         *
         * @public
         * @method toggleButtons
         */
        toggleButtons: function (plugin) {
            var me = this,
                disabledClass = me.opts.disabledClass,
                scale,
                minScale,
                maxScale;

            if (!plugin) {
                return;
            }

            scale = plugin.getScale();
            minScale = plugin.getMinScale();
            maxScale = plugin.getMaxScale();

            me.$zoomResetBtn.toggleClass(disabledClass, scale === minScale);
            me.$zoomOutBtn.toggleClass(disabledClass, scale === minScale);
            me.$zoomInBtn.toggleClass(disabledClass, scale === maxScale);

            $.publish('plugin/swImageGallery/onToggleButtons', [ me ]);
        },

        /**
         * Destroys the plugin and removes
         * all elements created by the plugin.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                plugin = me.getImageSlider();

            if (plugin) {
                plugin.destroy();
            }

            me.$template.remove();
            me.$template = null;

            me.$zoomOutBtn.remove();
            me.$zoomResetBtn.remove();
            me.$zoomInBtn.remove();

            me._$imageContainer = null;
            me._$thumbContainer = null;
            me._$imageContainerClone = null;
            me._$thumbContainerClone = null;
        }
    });
})(jQuery, window);
