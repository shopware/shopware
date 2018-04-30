;(function($, window) {
    'use strict';

    /**
     * Shopware Add Article Plugin
     *
     * @example Button Element (can be pretty much every element)
     *
     * HTML:
     *
     * <button data-add-article="true" data-addArticleUrl="{url controller='checkout' action='addArticle' sAdd=$sArticle.ordernumber}">
     *     Jetzt bestellen
     * </button>
     *
     * @example Form
     *
     * HTML:
     *
     * <form data-add-article="true" data-eventName="submit">
     *     <input type="hidden" name="sAdd" value="SW10165"> // Contains the ordernumber of the article
     *     <input type="hidden" name="sQuantity" value"10"> // Optional (Default: 1). Contains the amount of articles to be added (Can also be an select box)
     *
     *     <button>In den Warenkorb</button>
     * </form>
     *
     *
     * You can either add an article by giving a specific url to the property "addArticleUrl" (First example)
     * or you can add hidden input fields to the element with name "sAdd" and "sQuantity" (Second example).
     *
     * JS:
     *
     * $('*[data-add-article="true"]').swAddArticle();
     *
     */
    $.plugin('swAddArticle', {

        defaults: {
            /**
             * Event name that the plugin listens to.
             *
             * @type {String}
             */
            'eventName': 'click',

            /**
             * The ajax url that the request should be send to.
             *
             * Default: myShop.com/(Controller:)checkout/(Action:)addArticle
             *
             * @type {String}
             */
            'addArticleUrl': window.controller['ajax_add_article'],

            /**
             * Default value that is used for the per-page amount when the current device is not mapped.
             * An extra option because the mapping table can be accidentally overwritten.
             *
             * @type {Number}
             */
            'sliderPerPageDefault': 3,

            /**
             * Whether or not the modal box should be shown.
             *
             * @type {Boolean}
             */
            'showModal': true,

            /**
             * Selector for the product slider in the add article modal box.
             *
             * @type {String}
             */
            'productSliderSelector': '.js--modal .product-slider'
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
            var me = this,
                opts = me.opts;

            // Applies HTML data attributes to the current options
            me.applyDataAttributes();

            opts.showModal = !!opts.showModal && opts.showModal !== 'false';

            // Will be automatically removed when destroy() is called.
            me._on(me.$el, opts.eventName, $.proxy(me.sendSerializedForm, me));

            // Close modal on continue shopping button
            $('body').delegate('*[data-modal-close="true"]', 'click.modal', $.proxy(me.closeModal, me));

            StateManager.addPlugin(opts.productSliderSelector, 'swProductSlider');
        },

        /**
         * Gets called when the element was triggered by the given event name.
         * Serializes the plugin element {@link $el} and sends it to the given url.
         * When the ajax request was successful, the {@link initModalSlider} will be called.
         *
         * @public
         * @event sendSerializedForm
         * @param {jQuery.Event} event
         */
        sendSerializedForm: function (event) {
            event.preventDefault();

            var me = this,
                opts = me.opts,
                $el = me.$el,
                ajaxData = $el.serialize();

            ajaxData += '&isXHR=1';

            if (opts.showModal) {
                $.loadingIndicator.open({
                    'openOverlay': true
                });
            }

            $.publish('plugin/swAddArticle/onBeforeAddArticle', [ me, ajaxData ]);

            $.ajax({
                'data': ajaxData,
                'dataType': 'jsonp',
                'url': opts.addArticleUrl,
                'success': function (result) {
                    $.publish('plugin/swAddArticle/onAddArticle', [ me, result ]);

                    if (!opts.showModal) {
                        return;
                    }

                    $.loadingIndicator.close(function () {
                        $.modal.open(result, {
                            width: 750,
                            sizing: 'content',
                            onClose: me.onCloseModal.bind(me)
                        });

                        picturefill();

                        StateManager.updatePlugin(opts.productSliderSelector, 'swProductSlider');

                        $.publish('plugin/swAddArticle/onAddArticleOpenModal', [ me, result ]);
                    });
                }
            });
        },

        /**
         * Closes the modal by continue shopping link.
         *
         * @public
         * @event closeModal
         */
        closeModal: function (event) {
            event.preventDefault();

            $.modal.close();

            $.publish('plugin/swAddArticle/onCloseModal', [ this ]);
        },

        /**
         * Gets called when the modal box is closing.
         * Destroys the product slider when its available.
         *
         * @public
         * @event onCloseModal
         */
        onCloseModal: function () {
            var me = this;

            StateManager.destroyPlugin(me.opts.productSliderSelector, 'swProductSlider');

            $.publish('plugin/swAddArticle/onCloseModal', [ me ]);
        }
    });
})(jQuery, window);
