;(function($) {
    'use strict';

    $.plugin('swShippingPayment', {

        defaults: {
            radioSelector: 'input.auto_submit[type=radio]',
        },

        /**
         * Plugin constructor.
         */
        init: function () {
            var me = this;

            me.applyDataAttributes();
            me.registerEvents();
        },

        /**
         * Registers all necessary event listener.
         */
        registerEvents: function () {
            var me = this;

            me.$el.on('change', me.opts.radioSelector, $.proxy(me.onInputChanged, me));
            $.publish('plugin/swShippingPayment/onRegisterEvents', [ me ]);
        },

        /**
         * Called on change event of the radio fields.
         */
        onInputChanged: function (event) {
            var me = this,
                id = event.target.value;

            $.publish('plugin/swShippingPayment/onInputChangedBefore', [ me ]);
            $('.custom_template').hide();
            $('#custom_template_' + id).show();
            $.publish('plugin/swShippingPayment/onInputChanged', [ me ]);
        },

        /**
         * Destroy method of the plugin.
         * Removes attached event listener.
         */
        destroy: function() {
            var me = this;
            me.$el.off('change', me.opts.radioSelector);
            me._destroy();
        }
    });
})(jQuery);
