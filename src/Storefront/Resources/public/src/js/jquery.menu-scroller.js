;(function ($) {
    'use strict';

    /**
     * Shopware Menu Scroller Plugin
     *
     * @example
     *
     * HTML:
     *
     * <div class="container">
     *     <ul class="my--list">
     *         <li>
     *             <!-- Put any element you want in here -->
     *         </li>
     *
     *         <li>
     *             <!-- Put any element you want in here -->
     *         </li>
     *
     *         <!-- More li elements -->
     *     </ul>
     * </div>
     *
     * JS:
     *
     * $('.container').swMenuScroller();
     */
    $.plugin('swMenuScroller', {

        /**
         * Default options for the menu scroller plugin
         *
         * @public
         * @property defaults
         * @type {Object}
         */
        defaults: {

            /**
             * CSS selector for the starting active item.
             * On initialisation, the slider will jump to it so it's visible..
             *
             * @type {String}
             */
            activeItemSelector: '.is--active',

            /**
             * CSS selector for the element listing
             *
             * @type {String}
             */
            listSelector: '*[class$="--list"]',

            /**
             * CSS class which will be added to the wrapper / this.$el
             *
             * @type {String}
             */
            wrapperClass: 'js--menu-scroller',

            /**
             * CSS class which will be added to the listing
             *
             * @type {String}
             */
            listClass: 'js--menu-scroller--list',

            /**
             * CSS class which will be added to every list item
             *
             * @type {String}
             */
            itemClass: 'js--menu-scroller--item',

            /**
             * CSS class(es) which will be set for the left arrow
             *
             * @type {String}
             */
            leftArrowClass: 'js--menu-scroller--arrow left--arrow',

            /**
             * CSS class(es) which will be set for the right arrow
             *
             * @type {String}
             */
            rightArrowClass: 'js--menu-scroller--arrow right--arrow',

            /**
             * CSS Class for the arrow content to center the arrow text.
             *
             * @type {String}
             */
            arrowContentClass: 'arrow--content',

            /**
             * Content of the left arrow.
             * Default it's an arrow pointing left.
             *
             * @type {String}
             */
            leftArrowContent: '&#58897;',

            /**
             * Content of the right arrow.
             * Default it's an arrow pointing right.
             *
             * @type {String}
             */
            rightArrowContent: '&#58895;',

            /**
             * Amount of pixels the plugin should scroll per arrow click.
             *
             * There is also a additional option:
             *
             * 'auto': the visible width will be taken.
             *
             * @type {String|Number}
             */
            scrollStep: 'auto',

            /**
             * Time in milliseconds the slide animation needs.
             *
             * @type {Number}
             */
            animationSpeed: 400,

            /**
             * Offset of the scroll position when we jump to the active item.
             *
             * @type {Number}
             */
            arrowOffset: 25
        },

        /**
         * Default plugin initialisation function.
         * Sets all needed properties, creates the slider template
         * and registers all needed event listeners.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                opts = me.opts,
                $activeChild;

            // Apply all given data attributes to the options
            me.applyDataAttributes();

            /**
             * Length in pixel the menu has to scroll when clicked on a button.
             *
             * @private
             * @property scrollStep
             * @type {Number}
             */
            me.scrollStep = (opts.scrollStep === 'auto') ? me.$el.width() / 2 : parseFloat(opts.scrollStep);

            /**
             * Length in pixel the menu has to scroll when clicked on a button.
             *
             * @private
             * @property $list
             * @type {jQuery}
             */
            me.$list = me.$el.find(opts.listSelector);

            /**
             * The offset based on the current scroll bar height of the list.
             *
             * @private
             * @property scrollBarOffset
             * @type {Number}
             */
            me.scrollBarOffset = 0;

            // Initializes the template by adding classes to the existing elements and creating the buttons
            me.initTemplate();

            // Register window resize and button events
            me.registerEvents();

            // Update the button visibility
            me.updateButtons();

            $activeChild = me.$list.children(opts.activeItemSelector);

            if ($activeChild.length) {
                me.jumpToElement($activeChild);
            }
        },

        /**
         * Creates all needed control items and adds plugin classes
         *
         * @public
         * @method initTemplate
         */
        initTemplate: function () {
            var me = this,
                opts = me.opts,
                $el = me.$el,
                $list = me.$list;

            $el.addClass(opts.wrapperClass);

            $list.addClass(opts.listClass);

            me.updateScrollBarOffset();

            // Add the item class to every list item
            $list.children().addClass(opts.itemClass);

            me.$leftArrow = $('<div>', {
                'html': $('<span>', {
                    'class': opts.arrowContentClass,
                    'html': opts.leftArrowContent
                }),
                'class': opts.leftArrowClass
            }).appendTo($el);

            me.$rightArrow = $('<div>', {
                'html': $('<span>', {
                    'class': opts.arrowContentClass,
                    'html': opts.rightArrowContent
                }),
                'class': opts.rightArrowClass
            }).appendTo($el);

            $.publish('plugin/swMenuScroller/onInitTemplate', [ me ]);
        },

        /**
         * Creates all needed control items and adds plugin classes
         *
         * @public
         * @method initTemplate
         */
        updateScrollBarOffset: function () {
            var me = this,
                $list = me.$list,
                offset;

            offset = me.scrollBarOffset = Math.min(Math.abs($list[0].scrollHeight - $list.height()) * -1, me.scrollBarOffset);

            $list.css({
                'bottom': offset,
                'margin-top': offset
            });

            $.publish('plugin/swMenuScroller/onUpdateScrollBarOffset', [ me, offset ]);
        },

        /**
         * Registers the listener for the window resize.
         * Also adds the click/tap listeners for the navigation buttons.
         *
         * @public
         * @method registerEvents
         */
        registerEvents: function () {
            var me = this;

            StateManager.on('resize', me.updateResize, me);

            me._on(me.$leftArrow, 'click touchstart', $.proxy(me.onLeftArrowClick, me));
            me._on(me.$rightArrow, 'click touchstart', $.proxy(me.onRightArrowClick, me));

            me._on(me.$list, 'scroll', $.proxy(me.updateButtons, me));

            $.publish('plugin/swMenuScroller/onRegisterEvents', [ me ]);
        },

        /**
         * Will be called when the window resizes.
         * Calculates the new width and scroll step.
         * Refreshes the button states.
         *
         * @public
         * @method updateResize
         */
        updateResize: function () {
            var me = this,
                opts = me.opts,
                viewPortWidth = me.$el.width();

            me.updateScrollBarOffset();

            if (opts.scrollStep === 'auto') {
                me.scrollStep = viewPortWidth / 2;
            }

            me.updateButtons();

            $.publish('plugin/swMenuScroller/onUpdateResize', [ me ]);
        },

        /**
         * Called when left arrow was clicked / touched.
         * Adds the negated offset step to the offset.
         *
         * @public
         * @method onLeftArrowClick
         * @param {jQuery.Event} event
         */
        onLeftArrowClick: function (event) {
            event.preventDefault();

            var me = this;

            me.addOffset(me.scrollStep * -1);

            $.publish('plugin/swMenuScroller/onLeftArrowClick', [ me ]);
        },

        /**
         * Called when right arrow was clicked / touched.
         * Adds the offset step to the offset.
         *
         * @public
         * @method onRightArrowClick
         * @param {jQuery.Event} event
         */
        onRightArrowClick: function (event) {
            event.preventDefault();

            var me = this;

            me.addOffset(me.scrollStep);

            $.publish('plugin/swMenuScroller/onRightArrowClick', [ me ]);
        },

        /**
         * Adds the given offset relatively to the current offset.
         *
         * @public
         * @method addOffset
         * @param {Number} offset
         */
        addOffset: function (offset) {
            this.setOffset(this.$list.scrollLeft() + offset, true);
        },

        /**
         * Sets the absolute scroll offset.
         * Min / Max the offset so the menu stays in bounds.
         *
         * @public
         * @method setOffset
         * @param {Number} offset
         * @param {Boolean} animate
         */
        setOffset: function (offset, animate) {
            var me = this,
                opts = me.opts,
                $list = me.$list,
                maxWidth = $list.prop('scrollWidth') - me.$el.width(),
                newPos = Math.max(0, Math.min(maxWidth, offset));

            if (animate !== false) {
                $list.stop(true).animate({
                    'scrollLeft': newPos
                }, opts.animationSpeed, $.proxy(me.updateButtons, me));

                $.publish('plugin/swMenuScroller/onSetOffset', [ me, offset, animate ]);
                return;
            }

            $list.scrollLeft(newPos);

            me.updateButtons();

            $.publish('plugin/swMenuScroller/onSetOffset', [ me, offset, animate ]);
        },

        /**
         * Updates the buttons status and toggles their visibility.
         *
         * @public
         * @method updateButtons
         */
        updateButtons: function () {
            var me = this,
                $list = me.$list,
                elWidth = me.$el.width(),
                listWidth = $list.prop('scrollWidth'),
                scrollLeft = $list.scrollLeft();

            me.$leftArrow.toggle(scrollLeft > 0);
            me.$rightArrow.toggle(listWidth > elWidth && scrollLeft < (listWidth - elWidth));

            $.publish('plugin/swMenuScroller/onUpdateButtons', [ me, me.$leftArrow, me.$rightArrow ]);
        },

        /**
         * Jumps to the given active element on plugin initialisation.
         *
         * @public
         * @method jumpToElement
         */
        jumpToElement: function ($el) {
            var me = this,
                $list = me.$list,
                elWidth = me.$el.width(),
                scrollLeft = $list.scrollLeft(),
                leftPos = $el.position().left,
                rightPos = leftPos + $el.outerWidth(true),
                newPos;

            if (leftPos > scrollLeft && rightPos > scrollLeft + elWidth) {
                newPos = rightPos - elWidth + me.opts.arrowOffset;
            } else {
                newPos = Math.min(leftPos - me.$leftArrow.width(), scrollLeft);
            }

            me.setOffset(newPos, false);

            $.publish('plugin/swMenuScroller/onJumpToElement', [ me, $el, newPos ]);
        },

        /**
         * Removed all listeners, classes and values from this plugin.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            var me = this,
                opts = me.opts;

            StateManager.off('resize', me.updateResize, me);

            me.$el.removeClass(opts.wrapperClass);
            me.$list.removeClass(opts.listClass);

            me.$list.css({
                'bottom': '',
                'margin-top': ''
            });

            // Remove the item class of every list item
            me.$list.children().removeClass(opts.itemClass);

            me.$leftArrow.remove();
            me.$rightArrow.remove();

            me._destroy();
        }
    });
}(jQuery));
