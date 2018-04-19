;(function($, window) {
    'use strict';

    /**
     * Shopware Address Editor Plugin.
     *
     * The plugin handles the address editing of a given address or creation of a new one. You can specify
     * additional parameters to do various operations afterwards. See property section extra parameters for
     * more information.
     *
     * Example usage:
     * ```
     * <button class="btn" data-address-editor="true" data-id="123">
     *   Change address
     * </button>
     * ``
     */
    $.plugin('swAddressEditor', {

        /** Your default options */
        defaults: {
            /**
             * Id of an address which should be edited
             *
             * @int id
             */
            id: null,

            /**
             * Submit button class to dis/enable them later on
             *
             * @string submitButtonSelector
             */
            submitButtonSelector: '.address--form-submit',

            /**
             * Width of the selection
             *
             * @string width
             */
            width: 650,

            /**
             * Height of the selection
             *
             * @string height
             */
            height: '80%',

            /**
             * Modal sizing
             *
             * @string sizing
             */
            sizing: 'content',

            /**
             * Extra parameters to trigger specific actions afterwards
             *
             * Comma separated list of session keys to be filled with address id
             *
             * @string sessionKey
             */
            sessionKey: '',

            /**
             * Set the address as default billing address
             *
             * @boolean setDefaultBillingAddress
             */
            setDefaultBillingAddress: null,

            /**
             * Set the address as default shipping address
             *
             * @boolean setDefaultShippingAddress
             */
            setDefaultShippingAddress: null,

            /**
             * Display the address selection after the editor has been closed
             *
             * @boolean showSelectionOnClose
             */
            showSelectionOnClose: false
        },

        /**
         * Initializes the plugin
         *
         * @returns {Plugin}
         */
        init: function () {
            var me = this;

            me.applyDataAttributes(true);

            me._on(me.$el, 'click', $.proxy(me.onClick, me));

            $.publish('plugin/swAddressEditor/onRegisterEvents', [ me ]);
        },

        /**
         * Handle click event and delegate to the open() method
         *
         * @param event
         */
        onClick: function(event) {
            var me = this;

            event.preventDefault();

            $.publish('plugin/swAddressEditor/onBeforeClick', [ me, me.opts.id ]);

            if (me.opts.id) {
                me.open(me.opts.id);
            } else {
                me.open();
            }

            $.publish('plugin/swAddressEditor/onAfterClick', [ me, me.opts.id ]);
        },

        /**
         * Open modal and load data if addressId is a valid number
         *
         * @param {int} addressId
         */
        open: function(addressId) {
            var me = this,
                sizing = me.opts.sizing,
                maxHeight = 0,
                requestData = {
                    id: addressId || null,
                    extraData: {
                        sessionKey: me.opts.sessionKey,
                        setDefaultBillingAddress: me.opts.setDefaultBillingAddress,
                        setDefaultShippingAddress: me.opts.setDefaultShippingAddress
                    }
                };

            if (window.StateManager._getCurrentDevice() === 'mobile') {
                sizing = 'auto';
            } else {
                maxHeight = me.opts.height;
            }

            // reset modal
            $.modal.close();
            $.loadingIndicator.open();

            $.publish('plugin/swAddressEditor/onBeforeOpen', [ me, requestData ]);

            // Ajax request to fetch available addresses
            $.ajax({
                'url': window.controller['ajax_address_editor'],
                'data': requestData,
                'success': function(data) {
                    $.loadingIndicator.close(function() {
                        $.subscribe(me.getEventName('plugin/swModal/onOpen'), $.proxy(me._onSetContent, me));

                        $.modal.open(data, {
                            width: me.opts.width,
                            height: me.opts.height,
                            maxHeight: maxHeight,
                            sizing: sizing,
                            additionalClass: 'address-manager--modal address-manager--editor',
                            addressId: addressId
                        });

                        $.unsubscribe(me.getEventName('plugin/swModal/onOpen'));
                    });

                    $.publish('plugin/swAddressEditor/onAddressFetchSuccess', [ me, data ]);
                }
            });

            $.publish('plugin/swAddressEditor/onAfterOpen', [ me ]);
        },

        /**
         * Callback from $.modal setContent method
         *
         * @param event
         * @param $modal
         * @private
         */
        _onSetContent: function(event, $modal) {
            var me = this;

            me._registerPlugins();
            me._bindButtonAction($modal);
        },

        /**
         * Re-register plugins to enable them in the modal
         * @private
         */
        _registerPlugins: function() {
            window.StateManager
                .addPlugin('div[data-register="true"]', 'swRegister')
                .addPlugin('*[data-preloader-button="true"]', 'swPreloaderButton');

            $.publish('plugin/swAddressEditor/onRegisterPlugins', [ this ]);
        },

        /**
         * Registers listeners for the click event on the "change address" buttons. The buttons contain the
         * needed data for the address selection. It then sends an ajax post request to the form
         * action
         *
         * @param $modal
         * @private
         */
        _bindButtonAction: function($modal) {
            var me = this,
                $submitButtons = $modal._$content.find(me.opts.submitButtonSelector),
                $actionInput = $modal._$content.find('input[name=saveAction]');

            $.publish('plugin/swAddressEditor/onBeforeBindButtonAction', [ me, $modal ]);

            // hook into submit button click to eventually update the saveAction value bound to data-value
            $submitButtons.on('click', function(event) {
                var $elem = $(this);

                event.preventDefault();

                $actionInput.val($elem.attr('data-value'));
                $elem.closest('form').submit();
            });

            // submit form via ajax
            $modal._$content
                .find('form')
                .on('submit', function(event) {
                    var $target = $(event.target),
                        actionData = {
                            id: $modal.options.addressId || null
                        };

                    me._resetErrorMessage($modal);
                    me._disableSubmitButtons($modal);

                    event.preventDefault();

                    $.each($target.serializeArray(), function() {
                        actionData[this.name] = this.value;
                    });

                    $.publish('plugin/swAddressEditor/onBeforeSave', [ me, actionData ]);

                    // send data to api endpoint
                    $.ajax({
                        url: $target.attr('action'),
                        data: actionData,
                        method: 'POST',
                        success: function(response) {
                            me.onSave($modal, response);
                        }
                    });
                });

            $.publish('plugin/swAddressEditor/onAfterBindButtonAction', [ me, $modal ]);
        },

        /**
         * Callback after the API has been called
         */
        onSave: function($modal, response) {
            var me = this;

            $.publish('plugin/swAddressEditor/onAfterSave', [ me, $modal, response ]);

            if (response.success === true) {
                if (me.opts.showSelectionOnClose) {
                    $.addressSelection.openPrevious();
                } else {
                    window.location.reload();
                }
            } else {
                me._highlightErrors($modal, response.errors);
                me._enableSubmitButtons($modal);
            }
        },

        /**
         * Display error container and highlight the fields containing errors
         *
         * @param $modal
         * @param errors
         * @private
         */
        _highlightErrors: function($modal, errors) {
            var fieldPrefix = $modal._$content.find('.address-form--panel').attr('data-prefix') || 'address';

            $modal._$content.find('.address-editor--errors').removeClass('is--hidden');

            $.each(errors, function(field) {
                $modal._$content.find('[name="' + fieldPrefix + '[' + field + ']"]').addClass('has--error');
            });
        },

        /**
         * Hide error container in popup
         *
         * @param $modal
         * @private
         */
        _resetErrorMessage: function($modal) {
            $modal._$content.find('.address-editor--errors').addClass('is--hidden');
        },

        /**
         * Disable submit buttons to prevent multiple submissions
         *
         * @param $modal
         * @private
         */
        _disableSubmitButtons: function($modal) {
            var me = this;
            $modal._$content.find(me.opts.submitButtonSelector).attr('disabled', 'disabled');
        },

        /**
         * Reset state of preloader plugin and remove disable attribute
         *
         * @param $modal
         * @private
         */
        _enableSubmitButtons: function($modal) {
            var me = this;

            $modal._$content
                .find(me.opts.submitButtonSelector)
                .removeAttr('disabled')
                .data('plugin_swPreloaderButton')
                .reset();
        }
    });
})(jQuery, window);
