;(function($, window) {
    'use strict';

    $.plugin('swCookiePermission', {

        defaults: {

            /**
             * Class name to show and hide the cookiePermission element.
             *
             * @property isHiddenClass
             * @type {string}
             */
            isHiddenClass: 'is--hidden',

            /**
             * Selector of the accept button for select the button and register events on it.
             *
             * @property acceptButtonSelector
             * @type {string}
             */
            acceptButtonSelector: '.cookie-permission--accept-button',

            /**
             * Selector of the privacy statement link "More information" to select and prepare the href property.
             *
             * @property privacyLinkSelector
             * @type {string}
             */
            privacyLinkSelector: '.cookie-permission--privacy-link',

            /**
             * The current shopId for create the storageKey
             *
             * @property shopId
             * @type {number}
             */
            shopId: 0,

            /**
             * The shop host url for creating the data privacy statement link
             *
             * @property host
             * @type {string}
             */
            urlPrefix: ''
        },

        /**
         * The key for the local storage. By this key we save the acceptance of the user.
         *
         * @property cookieStorageKeyPrefix
         * @type {string}
         */
        cookieStorageKeyPrefix: 'hide-cookie-permission',

        /**
         * Default plugin initialisation function.
         * Sets all needed properties, adds classes and registers all needed event listeners.
         *
         * @public
         * @method init
         */
        init: function() {
            var me = this;

            me.applyDataAttributes();

            me.createProperties();
            me.preparePrivacyLink();
            me.registerEvents();
            me.displayCookiePermission(function(display) {
                if (display) {
                    me.showElement();
                }
            });
        },

        /**
         * Creates the required plugin properties
         *
         * @public
         * @method createProperties
         */
        createProperties: function() {
            var me = this;

            me.$privacyLink = me.$el.find(me.opts.privacyLinkSelector);
            me.$acceptButton = me.$el.find(me.opts.acceptButtonSelector);
            me.storageKey = me.createStorageKey();
            me.storage = window.StorageManager.getLocalStorage();
        },

        /**
         * Create and set if required a full qualified url as prefix for the privacy link href attribute.
         *
         * @public
         * @method preparePrivacyLink
         */
        preparePrivacyLink: function() {
            var me = this,
                prefix = me.opts.urlPrefix,
                href;

            if (!me.$privacyLink) {
                return;
            }

            href = me.$privacyLink.attr('href') || '';

            if (href.match(/^(http:|https:)/)) {
                return;
            }

            if (href.match(/^\//)) {
                prefix = me.opts.urlPrefix.replace(/(\/)$/, '');
            }

            me.$privacyLink.attr('href', [
                prefix,
                href
            ].join(''));
        },

        /**
         * Subscribes all required events.
         *
         * @public
         * @method registerEvents
         */
        registerEvents: function() {
            var me = this;

            me._on(me.$acceptButton, 'click', $.proxy(me.onAcceptButtonClick, me));
        },

        /**
         * Validates if cookie permission hint should be shown
         *
         * @param {function} callback
         */
        displayCookiePermission: function(callback) {
            var me = this;

            callback(!me.storage.getItem(me.storageKey));
        },

        /**
         * Creates the storageKey from the prefix and the shopId like the following example:
         *
         * hide-cookie-permission-1
         *
         * @public
         * @method createStorageKey
         * @returns {string}
         */
        createStorageKey: function() {
            var me = this,
                delimiter = '-';

            return [
                me.cookieStorageKeyPrefix,
                delimiter,
                me.opts.shopId
            ].join('');
        },

        /**
         * Event handler for the acceptButton click.
         *
         * @public
         * @method onAcceptButtonClick
         */
        onAcceptButtonClick: function() {
            var me = this;

            me.storage.setItem(me.storageKey, 'true');
            me.hideElement();
        },

        /**
         * Shows the cookiePermission element.
         *
         * @public
         * @method showElement
         */
        showElement: function() {
            var me = this;

            me.$el.removeClass(me.opts.isHiddenClass);
        },

        /**
         * Hides the cookiePermission element.
         *
         * @public
         * @method hideElement
         */
        hideElement: function() {
            var me = this;

            me.$el.addClass(me.opts.isHiddenClass);
        }
    });
}(jQuery, window));
