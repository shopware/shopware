;(function ($) {
    'use strict';

    $.plugin('swNewsletter', {

        init: function () {
            var me = this;

            me.$checkMail = me.$el.find('.newsletter--checkmail');
            me.$addionalForm = me.$el.find('.newsletter--additional-form');

            me._on(me.$checkMail, 'change', $.proxy(me.refreshAction, me));

            $.publish('plugin/swNewsletter/onRegisterEvents', [ me ]);

            me.$checkMail.trigger('change');
        },

        refreshAction: function (event) {
            var me = this,
                $el = $(event.currentTarget),
                val = $el.val();

            if (val == -1) {
                me.$addionalForm.hide();
            } else {
                me.$addionalForm.show();
            }

            $.publish('plugin/swNewsletter/onRefreshAction', [ me ]);
        },

        destroy: function () {
            this._destroy();
        }
    });
}(jQuery));
