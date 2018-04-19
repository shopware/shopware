;(function($) {
    'use strict';

    /**
     * Shopware Auto Submit Plugin
     *
     * @example
     *
     * HTML:
     *
     * <form method="GET" action="URL">
     *     <input type="checkbox" name="item1" value="1" data-auto-submit="true" />
     *     <input type="radio" name="item2" value="2" data-auto-submit="true" />
     *     <select name="item3" data-auto-submit="true">
     *         <option value="opt1" selected="selected">My option 1</option>
     *         <option value="opt2">My option 2</option>
     *         <option value="opt3">My option 3</option>
     *     </select>
     * </form>
     *
     * JS:
     *
     * $('form *[data-auto-submit="true"]').autoSubmit();
     *
     * If you now change either an input or an option in the select, the form will be submitted.
     *
     */
    $.plugin('swAutoSubmit', {

        defaults: {

            /**
             * Decide if loading indicator is shown until the form is submitted.
             *
             * @property loadingindicator
             * @type {Boolean}
             */
            'loadingindicator': true
        },

        /**
         * Default plugin initialisation function.
         * Registers an event listener on the change event.
         * When it's triggered, the parent form will be submitted.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this;

            me.applyDataAttributes();

            me.$form = $(me.$el.parents('form')[0]);

            // Will be automatically removed when destroy() is called.
            me._on(me.$el, 'change', $.proxy(me.onChangeSelection, me));

            $.publish('plugin/swAutoSubmit/onRegisterEvents', [ me ]);
        },

        onChangeSelection: function () {
            var me = this;

            if (me.opts.loadingindicator) {
                $.loadingIndicator.open({
                    closeOnClick: false
                });
            }

            me.$form.submit();
        }
    });
})(jQuery);
