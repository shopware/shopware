;(function ($) {
    /**
     * Shopware Tab Menu Plugin
     *
     * This plugin sets up a menu with tabs you can switch between.
     */
    $.plugin('swTabMenu', {

        defaults: {

            /**
             * Class that should be set on the plugin element when initializing
             *
             * @property pluginClass
             * @type {String}
             */
            'pluginClass': 'js--tab-menu',

            /**
             * Selector for the tab navigation list
             *
             * @property tabContainerSelector
             * @type {String}
             */
            'tabContainerSelector': '.tab--navigation',

            /**
             * Selector for a tab navigation item
             *
             * @property tabSelector
             * @type {String}
             */
            'tabSelector': '.tab--link',

            /**
             * Selector for the tab content list
             *
             * @property containerListSelector
             * @type {String}
             */
            'containerListSelector': '.tab--container-list',

            /**
             * Selector for the tab container in a tab container list.
             *
             * @property containerSelector
             * @type {String}
             */
            'containerSelector': '.tab--container',

            /**
             * Selector for the content element inside a tab container.
             *
             * @property contentSelector
             * @type {String}
             */
            'contentSelector': '.tab--content',

            /**
             * Class that will be applied to a content container and
             * its corresponding tab when the container has any content.
             *
             * @property hasContentClass
             * @type {String}
             */
            'hasContentClass': 'has--content',

            /**
             * Class that should be set on an active tab navigation item
             *
             * @property activeTabClass
             * @type {String}
             */
            'activeTabClass': 'is--active',

            /**
             * Class that should be set on an active tab content item
             *
             * @property activeContainerClass
             * @type {String}
             */
            'activeContainerClass': 'is--active',

            /**
             * Starting index of the tabs
             *
             * @property startIndex
             * @type {Number}
             */
            'startIndex': -1,

            /**
             * This option can make the tab menu container horizontally
             * scrollable when too many tab menu items are displayed.
             * The functionality is provided by the swMenuScroller plugin.
             *
             * @property scrollable
             * @type {Boolean}
             */
            'scrollable': false
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
                $el = me.$el,
                $container,
                $tab;

            me.applyDataAttributes();

            $el.addClass(opts.pluginClass);

            me.$tabContainer = $el.find(opts.tabContainerSelector);

            me.$containerList = $el.find(opts.containerListSelector);

            me.$tabs = me.$tabContainer.find(opts.tabSelector);

            me.$container = me.$containerList.find(opts.containerSelector);

            me.$container.each(function (i, el) {
                $container = $(el);
                $tab = $(me.$tabs.get(i));

                if ($container.find(opts.contentSelector).html().trim().length) {
                    $container.addClass(opts.hasContentClass);
                    $tab.addClass(opts.hasContentClass);

                    // When no start index is specified, we take the first tab with content.
                    if (opts.startIndex === -1) {
                        $tab.addClass(opts.activeTabClass);
                        opts.startIndex = i;
                    }
                }
            });

            if (me.opts.scrollable) {
                me.$el.swMenuScroller({
                    'listSelector': me.$tabContainer
                });
            }

            opts.startIndex = Math.max(opts.startIndex, 0);

            me._index = null;

            me.registerEventListeners();

            me.changeTab(opts.startIndex);
        },

        /**
         * This method registers the event listeners when when clicking
         * or tapping a tab navigation item.
         *
         * @public
         * @method registerEvents
         */
        registerEventListeners: function () {
            var me = this;

            me.$tabs.each(function (i, el) {
                me._on(el, 'click touchstart', $.proxy(me.changeTab, me, i));
            });

            $.publish('plugin/swTabMenu/onRegisterEvents', [ me ]);
        },

        /**
         * This method switches to a new tab depending on the passed index
         * If the give index is the same as the current active one, nothing happens.
         *
         * @public
         * @method changeTab
         * @param {Number} index
         * @param {jQuery.Event} event
         */
        changeTab: function (index, event) {
            var me = this,
                opts = me.opts,
                activeTabClass = opts.activeTabClass,
                activeContainerClass = opts.activeContainerClass,
                $tab,
                tabId,
                dataUrl,
                $container;

            if (event) {
                event.preventDefault();
            }

            if (index === me._index) {
                return;
            }

            me._index = index;

            $tab = $(me.$tabs.get(index));
            $container = $(me.$container.get(index));

            me.$tabContainer
                .find('.' + activeTabClass)
                .removeClass(activeTabClass);

            $tab.addClass(activeTabClass);

            me.$containerList
                .find('.' + activeContainerClass)
                .removeClass(activeContainerClass);

            $container.addClass(activeContainerClass);

            dataUrl = $tab.attr('data-url');
            tabId = $container.attr('data-tab-id');

            if ($tab.attr('data-mode') === 'remote' && dataUrl) {
                $container.load(dataUrl);
            }

            if (tabId !== undefined) {
                $.publish('onShowContent-' + tabId, [ me, index ]);
            }

            $.publish('plugin/swTabMenu/onChangeTab', [ me, index ]);
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
                menuScroller = me.$el.data('plugin_swMenuScroller');

            if (menuScroller !== undefined) {
                menuScroller.destroy();
            }

            me.$el.removeClass(me.opts.pluginClass);

            me._destroy();
        }
    });
})(jQuery);
