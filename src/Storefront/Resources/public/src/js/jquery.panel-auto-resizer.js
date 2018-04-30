;(function($) {
    'use strict';

    /**
     * Shopware Panel Auto Resizer Plugin
     *
     * This plugin allows you to automatically resize a bunch of panels to match their biggest height. By default,
     * the setting 'columns' is set to 0, which will calculate the height based on all children elements beneath the
     * plugin element. If you want to resize panels in a 2-column layout and their height should match the height
     * of their neighbour, you have to set 'columns' to 2.
     *
     * You can activate this plugin by setting `data-panel-auto-resizer="true"` on the parent element of the elements
     * to resize.
     */
    $.plugin('swPanelAutoResizer', {

        defaults: {
            /**
             * CSS class selector for panel headers
             */
            panelHeaderSelector: '.panel--header',

            /**
             * CSS class selector for panel bodies
             */
            panelBodySelector: '.panel--body',

            /**
             * CSS class selector for panel actions
             */
            panelFooterSelector: '.panel--actions',

            /**
             * Maximal height, set to NULL (default) if it should not be limited
             */
            maxHeight: null
        },

        /**
         * Cache property for children elements
         */
        $elChildren: null,

        /**
         * If set to true, the modal will center after resizing
         */
        isModal: false,

        /**
         * Automatic resizing of header, body and footer
         */
        init: function() {
            var me = this;

            me.applyDataAttributes();

            me.$elChildren = me.$el.children();
            me.isModal = me.$el.closest('.js--modal').length > 0;

            $.subscribe(me.getEventName('plugin/swPanelAutoResizer/onAfterSetHeight'), $.proxy(me._onAfterSetHeight, me));

            $.publish('plugin/swPanelAutoResizer/onInit', [ me ]);
            me.update();
            $.publish('plugin/swPanelAutoResizer/onAfterInit', [ me ]);
        },

        /**
         * Trigger a recalculation if the panel is nested any parent panel has been resized.
         *
         * @param event
         * @param context
         * @private
         */
        _onAfterSetHeight: function(event, context) {
            var me = this;

            if (me === context) {
                return;
            }

            if (me.$el.closest(context.$el).length > 0) {
                me._calculateColumns();
                me.resize();
            }
        },

        /**
         * Calculate how many columns need to be sized properly
         * based on their and their container's width
         *
         * @private
         */
        _calculateColumns: function() {
            var me = this,
                maxWidth = me.$el.width(),
                width = 0,
                columns = 0,
                childWidth = 0;

            $.each(me.$elChildren, function(index, child) {
                childWidth = $(child).width();

                if ((width + childWidth) > maxWidth) {
                    return;
                }

                width += childWidth;
                columns++;
            });

            me._columns = columns;
        },

        /**
         * Recalculate the columns and resize all elements
         *
         * @private
         */
        update: function() {
            var me = this;

            if (me._resizeTimeout) {
                window.clearTimeout(me._resizeTimeout);
            }

            me._resizeTimeout = window.setTimeout(function() {
                $.publish('plugin/swPanelAutoResizer/onUpdate', [ me ]);

                me._calculateColumns();
                me.resize();

                $.publish('plugin/swPanelAutoResizer/afterUpdate', [ me ]);
            }, 150);
        },

        /**
         * Calculate the maximum height of all given elements. It might be capped by the `maxHeight`
         * default option.
         *
         * @param $elements
         * @returns {number}
         */
        getMaxHeight: function ($elements) {
            var me = this,
                opts = me.opts,
                itemHeight = 0,
                height = 0;

            $.publish('plugin/swPanelAutoResizer/onGetMaxHeight', [ me ]);

            // set heights to auto to recalculate the actual content height
            $elements.each(function(index, childElement) {
                $(childElement).css('height', 'auto');
            });

            $elements.each(function(index, childElement) {
                itemHeight = $(childElement).height();
                if (itemHeight > height) {
                    height = itemHeight;
                }
            });

            if (opts.maxHeight !== null && opts.maxHeight < height) {
                height = opts.maxHeight;
            }

            $.publish('plugin/swPanelAutoResizer/onAfterGetMaxHeight', [ me, height ]);

            return height;
        },

        /**
         * Sets height on the given elements
         *
         * @param $elements
         * @param {number} height
         */
        setHeight: function($elements, height) {
            var me = this;

            if (height <= 0) {
                return;
            }

            $.publish('plugin/swPanelAutoResizer/onSetHeight', [ me ]);

            $.each($elements, function(index, childElement) {
                $(childElement).height(height);
            });

            $.publish('plugin/swPanelAutoResizer/onAfterSetHeight', [ me ]);
        },

        /**
         * Get maximal height and set the height of the elements
         *
         * @param {string} selector
         */
        resize: function(selector) {
            var me = this,
                height = 0,
                chunkItems = [],
                i = 0,
                childrenCount = me.$elChildren.length;

            // shortcut to resize all
            if (typeof selector === 'undefined') {
                me.resize(me.opts.panelHeaderSelector);
                me.resize(me.opts.panelBodySelector);
                me.resize(me.opts.panelFooterSelector);
                return;
            }

            $.publish('plugin/swPanelAutoResizer/onResize', [ me, selector ]);

            if (me._columns > 1) {
                for (i; i < childrenCount; i += me._columns) {
                    chunkItems = me.$elChildren
                        .slice(i, i + me._columns)
                        .map(function(index, child) {
                            return $(child).find(selector).first();
                        });

                    height = me.getMaxHeight(chunkItems);
                    me.setHeight(chunkItems, height);
                }
            } else {
                me.resetHeight();
            }

            me._centerModal();

            $.publish('plugin/swPanelAutoResizer/onAfterResize', [ me, selector ]);
        },

        /**
         * Call center() on modal
         *
         * @private
         */
        _centerModal: function() {
            if (this.isModal === false) {
                return;
            }

            $.modal.center();
        },

        /**
         * Sets the height back to 'auto' if the plugin gets disabled
         */
        resetHeight: function() {
            var me = this,
                opts = me.opts;

            var allSelectorClass = [
                opts.panelHeaderSelector,
                opts.panelBodySelector,
                opts.panelFooterSelector
            ].join(',');

            me.$elChildren.find(allSelectorClass).each(function(index, childElement) {
                $(childElement).css('height', 'auto');
            });
        },

        /**
         * Destroy the plugin and reset it's modified attributes
         */
        destroy: function() {
            var me = this;

            me.resetHeight();
            $.unsubscribe(me.getEventName('plugin/swPanelAutoResizer/onAfterSetHeight'));
            me._destroy();
        }

    });
})(jQuery);
