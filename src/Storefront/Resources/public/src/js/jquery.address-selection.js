;(function($, window) {
    'use strict';

    $.addressSelection = {

        /**
         * @string _name
         */
        _name: 'addressSelection',

        /**
         * Holds the options of the last opened selection
         *
         * @object _previousOptions
         */
        _previousOptions: {},

        /** Your default options */
        defaults: {
            /**
             * Id of an address which should not be shown
             *
             * @int id
             */
            id: null,

            /**
             * Form selector for each address
             *
             * @string formSelector
             */
            formSelector: '.address-manager--selection-form',

            /**
             * Width of the selection
             *
             * @string width
             */
            width: '80%',

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
            setDefaultShippingAddress: null
        },

        /**
         * add namespace for events
         * @param event
         * @returns {string}
         */
        getEventName: function(event) {
            return event + '.' + this._name;
        },

        /**
         * Open the selection with the previous options
         */
        openPrevious: function () {
            this.open(this._previousOptions);
        },

        /**
         * open function for opening the selection modal. The available addresses will be
         * fetched as html from the api
         */
        open: function (options) {
            var me = this,
                sizing,
                extraData,
                maxHeight = 0;

            me.opts = $.extend({}, me.defaults, options);

            extraData = {
                sessionKey: me.opts.sessionKey,
                setDefaultBillingAddress: me.opts.setDefaultBillingAddress,
                setDefaultShippingAddress: me.opts.setDefaultShippingAddress
            };

            sizing = me.opts.sizing;

            me._previousOptions = Object.create(me.opts);

            if (window.StateManager._getCurrentDevice() === 'mobile') {
                sizing = 'auto';
            } else {
                maxHeight = me.opts.height;
            }

            // reset modal
            $.modal.close();
            $.loadingIndicator.open();

            $.publish('plugin/swAddressSelection/onBeforeAddressFetch', [ me ]);

            // Ajax request to fetch available addresses
            $.ajax({
                'url': window.controller['ajax_address_selection'],
                'data': {
                    id: me.opts.id,
                    extraData: extraData
                },
                'success': function(data) {
                    $.loadingIndicator.close(function() {
                        $.subscribe(me.getEventName('plugin/swModal/onOpen'), $.proxy(me._onSetContent, me));

                        $.modal.open(data, {
                            width: me.opts.width,
                            maxHeight: maxHeight,
                            additionalClass: 'address-manager--modal address-manager--selection',
                            sizing: sizing
                        });

                        $.unsubscribe(me.getEventName('plugin/swModal/onOpen'));
                    });

                    $.publish('plugin/swAddressSelection/onAddressFetchSuccess', [ me, data ]);
                }
            });
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
                .addPlugin('*[data-panel-auto-resizer="true"]', 'swPanelAutoResizer')
                .addPlugin('*[data-address-editor="true"]', 'swAddressEditor')
                .addPlugin('*[data-preloader-button="true"]', 'swPreloaderButton');

            $.publish('plugin/swAddressSelection/onRegisterPlugins', [ this ]);
        },

        /**
         * Registers listeners for the click event on the "select address" buttons. The buttons contain the
         * needed data for the address selection. It then sends an ajax post request to the provided
         * action
         *
         * @param $modal
         * @private
         */
        _bindButtonAction: function($modal) {
            var me = this;

            $.publish('plugin/swAddressSelection/onBeforeBindButtonAction', [ me, $modal ]);

            $modal._$content
                .find(me.opts.formSelector)
                .on('submit', function(event) {
                    var $target = $(event.target);

                    event.preventDefault();

                    $.publish('plugin/swAddressSelection/onBeforeSave', [ me, $target ]);

                    // send data to api endpoint
                    $.ajax({
                        method: $target.attr('method'),
                        url: $target.attr('action'),
                        data: $target.serialize(),
                        success: function(response) {
                            me.onSave($modal, response);
                        }
                    });
                });

            $.publish('plugin/swAddressSelection/onAfterBindButtonAction', [ me, $modal ]);
        },

        /**
         * Callback after the API has been called successfully
         */
        onSave: function($modal, response) {
            var me = this;

            $.publish('plugin/swAddressSelection/onAfterSave', [ me, $modal, response ]);

            window.location.reload();
        }
    };

    /**
     * Shopware Address Selector Plugin.
     *
     * The plugin handles the address selection. You need to set some extra data to make something happen.
     *
     * Usually you specify a list of sessionKey's or set the selected address as default billing or shipping address.
     *
     * Example usage:
     * ```
     * <button class="btn" data-address-selection="true" data-id="123" data-setDefaultBillingAddress="1">
     *   Select address
     * </button>
     * ``
     */
    $.plugin('swAddressSelection', {
        /**
         * Initializes the plugin
         *
         * @returns {Plugin}
         */
        init: function () {
            var me = this;

            me.opts = $.extend({}, Object.create($.addressSelection.defaults), me.opts);
            me.applyDataAttributes(true);

            me._on(me.$el, 'click', $.proxy(me.onClick, me));

            $.publish('plugin/swAddressSelection/onRegisterEvents', [ me ]);
        },

        /**
         * Click callback
         * @param event
         */
        onClick: function(event) {
            event.preventDefault();
            $.addressSelection.open(this.opts);
        }
    });
})(jQuery, window);
