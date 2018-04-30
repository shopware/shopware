;(function ($, Modernizr) {
    'use strict';

    /**
     * Sub Category Navigation plugin
     *
     * The plugin provides an category slider inside the off canvas menu. The categories and sub categories
     * could be fetched by ajax calls and uses a CSS3 `transitions` to slide in or out. The main sidebar will not
     * be overwritten. The categories slider plugin uses two overlays to interact.
     *
     * @example usage
     * ```
     *    <div data-subcategory-nav="true"
     *      data-mainCategoryId="{$Shop->get('parentID')}"
     *      data-categoryId="{$sCategoryContent.id}"
     *      data-fetchUrl="{url module=widgets controller=listing action=getCategory categoryId={$sCategoryContent.id}}"></div>
     *
     *    $('*[data-subcategory-nav="true"]').swSubCategoryNav();
     * ```
     */
    $.plugin('swSubCategoryNav', {

        defaults: {

            /**
             * Whether or not the plugin is enabled or not.
             *
             * @property enabled
             * @type {Boolean}
             */
            'enabled': true,

            /**
             * Event name(s) used for registering the events to navigate
             *
             * @property eventName
             * @type {String}
             */
            'eventName': 'click',

            /**
             * Selector for a single navigation
             *
             * @property sidebarCategorySelector
             * @type {String}
             */
            'sidebarCategorySelector': '.sidebar--navigation',

            /**
             * Selector for the back buttons.
             *
             * @property backwardsSelector
             * @type {String}
             */
            'backwardsSelector': '.link--go-back',

            /**
             * Selector for the forward buttons.
             *
             * @property forwardSelector
             * @type {String}
             */
            'forwardsSelector': '.link--go-forward',

            /**
             * Selector for the main menu buttons.
             *
             * @property mainMenuSelector
             * @type {String}
             */
            'mainMenuSelector': '.link--go-main',

            /**
             * Selector for the wrapper of the sidebar navigation.
             * This wrapper will contain the main menu.
             *
             * @property sidebarWrapperSelector
             * @type {String}
             */
            'sidebarWrapperSelector': '.sidebar--categories-wrapper',

            /**
             * ID of the root category ID of the current shop.
             * This is used to determine if the user switches to the main
             * menu when clicking on a back button.
             *
             * @property mainCategoryId
             * @type {Number}
             */
            'mainCategoryId': null,

            /**
             * Category ID of the current page.
             * When this and fetchUrl is set, the correct slide will be loaded.
             *
             * @property categoryId
             * @type {Number}
             */
            'categoryId': null,

            /**
             * URL to get the current navigation slide.
             * When this and categoryID is set, the correct slide will be loaded.
             *
             * @property fetchUrl
             * @type {String}
             */
            'fetchUrl': '',

            /**
             * Selector for a overlay navigation slide.
             *
             * @property overlaySelector
             * @type {String}
             */
            'overlaySelector': '.offcanvas--overlay',

            /**
             * Selector for the whole sidebar itself.
             *
             * @property sidebarMainSelector
             * @type {String}
             */
            'sidebarMainSelector': '.sidebar-main',

            /**
             * Selector for the mobile navigation.
             *
             * @property mobileNavigationSelector
             * @type {String}
             */
            'mobileNavigationSelector': '.navigation--smartphone',

            /**
             * Loading class for the ajax calls.
             * This class will be used for a loading item.
             * This item will be appended to the clicked navigation item.
             *
             * @property loadingClass
             * @type {String}
             */
            'loadingClass': 'sidebar--ajax-loader',

            /**
             * Class that determines the existing slides to remove
             * them if no longer needed.
             *
             * @property backSlideClass
             * @type {String}
             */
            'backSlideClass': 'background',

            /**
             * Selector for the right navigation icon.
             * This icon will be hidden and replaced with the loading icon.
             *
             * @property iconRightSelector
             * @type {String}
             */
            'iconRightSelector': '.is--icon-right',

            /**
             * Class that will be appended to the main sidebar to
             * disable the scrolling functionality.
             *
             * @property disableScrollingClass
             * @type {String}
             */
            'disableScrollingClass': 'is--inactive',

            /**
             * Speed of the slide animations in milliseconds.
             *
             * @property animationSpeedIn
             * @type {Number}
             */
            'animationSpeedIn': 450,

            /**
             * Speed of the slide animations in milliseconds.
             *
             * @property animationSpeedOut
             * @type {Number}
             */
            'animationSpeedOut': 300,

            /**
             * Easing function for sliding a slide into the viewport.
             *
             * @property easingIn
             * @type {String}
             */
            'easingIn': 'cubic-bezier(.3,0,.15,1)',

            /**
             * Easing function for sliding a slide out of the viewport.
             *
             * @property easingOut
             * @type {String}
             */
            'easingOut': 'cubic-bezier(.02, .01, .47, 1)',

            /**
             * The animation easing used when transitions are not supported.
             *
             * @property easingFallback
             * @type {String}
             */
            'easingFallback': 'swing'
        },

        /**
         * Default plugin initialisation function.
         * Handle all logic and events for the category slider
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                transitionSupport = Modernizr.csstransitions,
                opts;

            // Overwrite plugin configuration with user configuration
            me.applyDataAttributes();

            opts = me.opts;

            // return, if no main category available
            if (!opts.enabled || !opts.mainCategoryId) {
                return;
            }

            /**
             * Reference of the main sidebar element.
             *
             * @private
             * @property $sidebar
             * @type {jQuery}
             */
            me.$sidebar = $(opts.sidebarMainSelector);

            /**
             * Wrapper of the navigation lists in the main navigation.
             *
             * @private
             * @property $sidebarWrapper
             * @type {jQuery}
             */
            me.$sidebarWrapper = $(opts.sidebarWrapperSelector);

            /**
             * Wrapper of the offcanvas animation
             *
             * @private
             * @property $navigation
             * @type {jQuery}
             */
            me.$navigation = $(opts.mobileNavigationSelector);
            me.$navigation.show();

            /**
             * Loading icon element that will be appended to the
             * clicked element on loading.
             *
             * @private
             * @property $loadingIcon
             * @type {jQuery}
             */
            me.$loadingIcon = $('<div>', {
                'class': opts.loadingClass
            });

            /**
             * Function used in jQuery based on CSS transition support.
             *
             * @private
             * @property slideFunction
             * @type {String}
             */
            me.slideFunction = transitionSupport ? 'transition' : 'animate';

            /**
             * Easing used for the slide in.
             *
             * @private
             * @property easingEffectIn
             * @type {String}
             */
            me.easingEffectIn = transitionSupport ? opts.easingIn : opts.easingFallback;

            /**
             * Easing used for the slide out.
             *
             * @private
             * @property easingEffectOut
             * @type {String}
             */
            me.easingEffectOut = transitionSupport ? opts.easingOut : opts.easingFallback;

            /**
             * Flag to determine whether or not a slide is in a current
             * animation or if an ajax call is still loading.
             *
             * @private
             * @property inProgress
             * @type {Boolean}
             */
            me.inProgress = false;

            // remove sub level unordered lists
            $(opts.sidebarCategorySelector + ' ul').not('.navigation--level-high').css('display', 'none');

            me.addEventListener();

            // fetch menu by category id if actual category is not the main category
            if (!opts.categoryId || !opts.fetchUrl || (opts.mainCategoryId == opts.categoryId)) {
                return;
            }

            $.get(opts.fetchUrl, function (template) {
                me.$sidebarWrapper.css('display', 'none');

                me.$sidebar.addClass(opts.disableScrollingClass).append(template);

                // add background class
                $(opts.overlaySelector).addClass(opts.backSlideClass);
            });
        },

        /**
         * Registers all needed event listeners.
         *
         * @public
         * @method addEventListener
         */
        addEventListener: function () {
            var me = this,
                opts = me.opts,
                $sidebar = me.$sidebar,
                eventName = opts.eventName;

            $sidebar.on(me.getEventName(eventName), opts.backwardsSelector, $.proxy(me.onClickBackButton, me));

            $sidebar.on(me.getEventName(eventName), opts.forwardsSelector, $.proxy(me.onClickForwardButton, me));

            $sidebar.on(me.getEventName(eventName), opts.mainMenuSelector, $.proxy(me.onClickMainMenuButton, me));

            $.publish('plugin/swSubCategoryNav/onRegisterEvents', [ me ]);
        },

        /**
         * Called when clicked on a back button.
         * Loads the overlay based on the parent id and fetch url.
         * When the no fetch url is available or the parent id is the same
         * as the main menu one, the slideToMainMenu function will be called.
         *
         * @public
         * @method onClickBackButton
         * @param {Object} event
         */
        onClickBackButton: function (event) {
            event.preventDefault();

            var me = this,
                $target = $(event.target),
                url = $target.attr('href'),
                parentId = ~~$target.attr('data-parentId');

            if (me.inProgress) {
                return;
            }

            me.inProgress = true;

            $.publish('plugin/swSubCategoryNav/onClickBackButton', [ me, event ]);

            // decide if there is a parent group or main sidebar
            if (!url || parentId === me.opts.mainCategoryId) {
                me.slideToMainMenu();
                return;
            }

            me.loadTemplate(url, me.slideOut, $target);
        },

        /**
         * Called when clicked on a forward button.
         * Loads the overlay based on the category id and fetch url.
         *
         * @public
         * @method onClickForwardButton
         * @param {Object} event
         */
        onClickForwardButton: function (event) {
            event.preventDefault();

            var me = this,
                $target = $(event.currentTarget),
                url = $target.attr('data-fetchUrl');

            if (me.inProgress) {
                return;
            }

            me.inProgress = true;

            $.publish('plugin/swSubCategoryNav/onClickForwardButton', [ me, event ]);

            // Disable scrolling on main menu
            me.$sidebar.addClass(me.opts.disableScrollingClass);

            me.loadTemplate(url, me.slideIn, $target);
        },

        /**
         * Called when clicked on a main menu button.
         * Calls the slideToMainMenu function.
         *
         * @public
         * @method onClickMainMenuButton
         * @param {Object} event
         */
        onClickMainMenuButton: function (event) {
            event.preventDefault();

            var me = this;

            if (me.inProgress) {
                return;
            }

            me.inProgress = true;

            $.publish('plugin/swSubCategoryNav/onClickMainMenuButton', [ me, event ]);

            me.slideToMainMenu();
        },

        /**
         * loads a template via ajax call
         *
         * @public
         * @method loadTemplate
         * @param {String} url
         * @param {Function} callback
         * @param {jQuery} $loadingTarget
         */
        loadTemplate: function (url, callback, $loadingTarget) {
            var me = this;

            $.publish('plugin/swSubCategoryNav/onLoadTemplateBefore', [ me ]);

            if (!$loadingTarget) {
                $.get(url, function (template) {
                    $.publish('plugin/swSubCategoryNav/onLoadTemplate', [ me ]);

                    callback.call(me, template);
                });
                return;
            }

            $loadingTarget.find(me.opts.iconRightSelector).fadeOut('fast');

            $loadingTarget.append(me.$loadingIcon);

            me.$loadingIcon.fadeIn();

            $.get(url, function (template) {
                me.$loadingIcon.hide();

                $.publish('plugin/swSubCategoryNav/onLoadTemplate', [ me ]);

                callback.call(me, template);
            });
        },

        /**
         * Sliding out the first level overlay and removes the slided overlay.
         *
         * @public
         * @method slideOut
         * @param {String} template
         */
        slideOut: function (template) {
            var me = this,
                opts = me.opts,
                $overlays,
                $slide;

            $.publish('plugin/swSubCategoryNav/onSlideOutBefore', [ me ]);

            me.$sidebar.append(template);

            // get all overlays
            $overlays = $(opts.overlaySelector);

            // flip background classes
            $overlays.toggleClass(opts.backSlideClass);

            $slide = $overlays.not('.' + opts.backSlideClass);

            $slide[me.slideFunction]({ 'left': 280 }, opts.animationSpeedOut, me.easingEffectOut, function () {
                $slide.remove();

                me.inProgress = false;

                $.publish('plugin/swSubCategoryNav/onSlideOut', [ me ]);
            });
        },

        /**
         * Slides a given template/slide into the viewport of the sidebar.
         * After the sliding animation is finished,
         * the previous slide will be removed.
         *
         * @public
         * @method slideIn
         * @param {String} template
         */
        slideIn: function (template) {
            var me = this,
                opts = me.opts,
                $overlays,
                $slide,
                $el;

            $.publish('plugin/swSubCategoryNav/onSlideInBefore', [ me ]);

            // hide main menu
            me.$sidebar.scrollTop(0);

            me.$sidebar.append(template);

            $overlays = $(opts.overlaySelector);

            $slide = $overlays.not('.' + opts.backSlideClass).css({
                'left': 280,
                'display': 'block'
            });

            $slide[me.slideFunction]({ 'left': 0 }, opts.animationSpeedIn, me.easingEffectIn, function () {
                // remove background layer
                $overlays.each(function (i, el) {
                    $el = $(el);

                    if ($el.hasClass(opts.backSlideClass)) {
                        $el.remove();
                    }
                });

                $slide.addClass(opts.backSlideClass);

                // hide main menu
                me.$sidebarWrapper.css('display', 'none');

                me.$navigation.hide().show(0);

                $slide.addClass(opts.backSlideClass);

                me.inProgress = false;

                $.publish('plugin/swSubCategoryNav/onSlideIn', [ me ]);
            });
        },

        /**
         * Slides all overlays out of the viewport and removes them.
         * That way the main menu will be uncovered.
         *
         * @public
         * @method slideToMainMenu
         */
        slideToMainMenu: function () {
            var me = this,
                opts = me.opts,
                $overlay = $(opts.overlaySelector);

            $.publish('plugin/swSubCategoryNav/onSlideToMainMenuBefore', [ me ]);

            // make the main menu visible
            me.$sidebarWrapper.css('display', 'block');

            // fade in arrow icons
            me.$sidebarWrapper.find(me.opts.iconRightSelector).fadeIn('slow');

            $overlay[me.slideFunction]({ 'left': 280 }, opts.animationSpeedOut, me.easingEffectOut, function () {
                $overlay.remove();

                // enable scrolling on main menu
                me.$sidebar.removeClass(opts.disableScrollingClass);

                me.inProgress = false;

                $.publish('plugin/swSubCategoryNav/onSlideToMainMenu', [ me ]);
            });
        },

        /**
         * Destroys the plugin by removing all events and references
         * of the plugin.
         * Resets all changed CSS properties to default.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                opts = me.opts,
                $sidebar = me.$sidebar,
                $sidebarWrapper = me.$sidebarWrapper;

            if ($sidebar) {
                $sidebar.off(me.getEventName(opts.eventName), '**');
            }

            me.$navigation.hide();

            // make category children visible
            $(opts.sidebarCategorySelector + ' ul').not('.navigation--level-high').css('display', 'block');

            // force sidebar to be shown
            if ($sidebarWrapper) {
                me.$sidebarWrapper.css('display', 'block');
            }

            // clear overlay
            $(opts.overlaySelector).remove();

            me._destroy();
        }
    });
}(jQuery, Modernizr));
