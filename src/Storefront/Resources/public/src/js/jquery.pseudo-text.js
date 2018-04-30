;(function($, window) {
    'use strict';

    /**
     * Pseudo text plugin
     *
     * The plugin provides an mechanism to duplicate the inserted text into another element. That behavior comes in
     * handy when you're dealing with complex layouts where the input element is placed outside of a ```form```-tag
     * but the value of the input needs to be send to the server-side.
     *
     * @example The example shows the basic usage:
     *
     * ```
     * <form>
     *    <textarea class="is--hidden my-field--hidden"></textarea>
     * </form>
     *
     * <textarea data-pseudo-text="true" data-selector=".my-field--hidden"></textarea>
     * ```
     */
    $.plugin('swPseudoText', {

        /**
         * Default settings for the plugin
         * @type {Object}
         */
        defaults: {
            /** @type {String} eventType - The event type which should be used to duplicate the content */
            eventType: 'keyup'
        },

        /**
         * Initializes the plugin and sets up the necessary event listeners.
         */
        init: function () {
            var me = this,
                selector = $(me.$el.attr('data-selector')),
                val;

            if (!selector.length) {
                throw new Error('Given selector does not match any element on the page.');
            }

            me._on(me.$el, me.opts.eventType, function() {
                val = me.$el.val();
                selector.val(val.length ? val : '');
            });
        }
    });
})(jQuery, window);
