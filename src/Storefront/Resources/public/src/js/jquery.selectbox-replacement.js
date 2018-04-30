;(function ($, window, document, undefined) {
    'use strict';

    /**
     * @Deprecated
     *
     * This plugin is only used as a polyfill for the old select field replacement.
     * It will be removed in a future release.
     *
     * Use the CSS-only version for styled select fields with the wrapper element.
     * For validation and other actions on the select field use the original select element
     * like you would do with any other form field.
     *
     * Example:
     * <div class="select-field">
     *    <select>
     *        <option>Option 1</option>
     *        <option>Option 2</option>
     *    </select>
     * </div>
     */
    $.plugin('swSelectboxReplacement', {

        defaults: {

            /** @property {String} Basic class name for the wrapper element. */
            'baseCls': 'js--fancy-select select-field',

            /** @property {String} The selector for the polyfill check. */
            'polyfillSelector': '.js--fancy-select, .select-field',

            /** @property {boolean} Copy all CSS classes to the wrapper element. */
            'compatibility': true
        },

        init: function () {
            var me = this;

            me.applyDataAttributes(true);

            me.createTemplate();

            return me;
        },

        createTemplate: function () {
            var me = this,
                $parent = me.$el.parent(me.opts.polyfillSelector),
                $wrapEl;

            if ($parent.length > 0) {
                return false;
            }

            $wrapEl = $('<div>', {
                'class': me.opts.baseCls
            });

            if (me.opts.compatibility) {
                $wrapEl.addClass(me.$el.attr('class'));
            }

            me.$wrapEl = me.$el.wrap($wrapEl);

            $.publish('plugin/swSelectboxReplacement/onCreateTemplate', [ me, me.$wrapEl ]);

            return me.$wrapEl;
        }
    });
})(jQuery, window, document);
