;(function($, window) {
    'use strict';

    /**
     * Shopware Captcha Plugin.
     *
     * @example
     *
     * Call the plugin on a node with a "data-src" attribute.
     * This attribute should provide the url for retrieving the captcha.
     *
     * HTML:
     *
     * <div data-src="CAPTCHA_REFRESH_URL" data-captcha="true"></div>
     *
     * JS:
     *
     * $('*[data-captcha="true"]').swCaptcha();
     *
     */
    $.plugin('swCaptcha', {

        /** @object Default configuration */
        defaults: {
            /**
             * Load the captcha image directly after initialization
             *
             * @property autoLoad
             * @type {Boolean}
             */
            autoLoad: false,

            /**
             * URL to captcha image
             *
             * @property src
             * @type {String}
             */
            src: '',

            /**
             * Indicates if the field contains errors
             *
             * @property hasError
             * @type {Boolean}
             */
            hasError: false
        },

        /**
         * Default plugin initialisation function.
         * Registers all needed event listeners and sends a request to load the captcha image.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                $el = me.$el;

            me.applyDataAttributes(true);

            if (!me.opts.src.length) {
                return;
            }

            if (me.opts.hasError) {
                window.setTimeout($.proxy(me.sendRequest, me), 1000);
                return;
            }

            if (me.opts.autoLoad) {
                me.sendRequest();
            } else {
                me.$form = $el.closest('form');
                me.$formInputs = me.$form.find(':input:not([name="__csrf_token"], select)');
                me._on(me.$formInputs, 'focus', $.proxy(me.onInputFocus, me));
            }
        },

        /**
         * Triggers _sendRequest and deactivates the focus listeners from input elements
         *
         * @private
         * @method onInputFocus
         */
        onInputFocus: function () {
            var me = this;

            me._off(me.$formInputs, 'focus');
            me.sendRequest();
        },

        /**
         * Sends an ajax request to the passed url and sets the result into the plugin's element.
         *
         * @public
         * @method _sendRequest
         */
        sendRequest: function () {
            var me = this,
                $el = me.$el;

            $.ajax({
                url: me.opts.src,
                cache: false,
                success: function (response) {
                    $el.html(response);
                    $.publish('plugin/swCaptcha/onSendRequestSuccess', [ me ]);
                }
            });

            $.publish('plugin/swCaptcha/onSendRequest', [ me ]);
        }
    });
})(jQuery, window);
