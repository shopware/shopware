;(function($) {
    'use strict';

    $.plugin('swDropdownMenu', {

        defaults: {
            activeCls: 'js--is--dropdown-active',
            preventDefault: true,
            closeOnBody: true
        },

        init: function () {
            var me = this;

            me._on(me.$el, 'touchstart click', $.proxy(me.onClickMenu, me));

            $.publish('plugin/swDropdownMenu/onRegisterEvents', [ me ]);
        },

        onClickMenu: function (event) {
            var me = this;

            if ($(event.target).is('.service--link, .compare--list, .compare--entry, .compare--link, .btn--item-delete, .compare--icon-remove')) {
                return;
            }

            if (me.opts.preventDefault) {
                event.preventDefault();
            }

            me.$el.toggleClass(me.opts.activeCls);

            if (me.opts.closeOnBody) {
                event.stopPropagation();
                $('body').on(me.getEventName('touchstart click'), $.proxy(me.onClickBody, me));
            }

            $.publish('plugin/swDropdownMenu/onClickMenu', [ me, event ]);
        },

        onClickBody: function(event) {
            var me = this;

            if ($(event.target).is('.service--link, .compare--list, .compare--entry, .compare--link, .btn--item-delete, .compare--icon-remove')) {
                return;
            }

            event.preventDefault();

            $('body').off(me.getEventName('touchstart click'));

            me.$el.removeClass(me.opts.activeCls);

            $.publish('plugin/swDropdownMenu/onClickBody', [ me, event ]);
        },

        destroy: function () {
            var me = this;

            me._destroy();
        }
    });
})(jQuery);
