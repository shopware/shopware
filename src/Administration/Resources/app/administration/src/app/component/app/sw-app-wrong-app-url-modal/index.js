/**
 * @sw-package framework
 */

import template from './sw-app-wrong-app-url-modal.html.twig';
import './sw-app-wrong-app-url-modal.scss';

const { Component } = Shopware;

const STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN = 'sw-app-wrong-app-url-modal-shown';

/**
 * @private
 */
Component.register('sw-app-wrong-app-url-modal', {
    template,

    emits: ['modal-close'],

    mixins: [Shopware.Mixin.getByName('notification')],

    data() {
        return {
            wasModalAlreadyShown: !!localStorage.getItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN),
            notification: {
                title: this.$tc('sw-app.component.sw-app-wrong-app-url-modal.title'),
                message: `${this.$tc('sw-app.component.sw-app-wrong-app-url-modal.explanation')}<br>
                     ${this.$tc('sw-app.component.sw-app-wrong-app-url-modal.textGetSupport')}`,
                actions: [
                    {
                        label: this.$tc('sw-app.component.sw-app-wrong-app-url-modal.labelLearnMoreButton'),
                        route: this.$tc('sw-app.component.sw-app-wrong-app-url-modal.linkToDocsArticle'),
                    },
                ],
                uuid: STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN,
            },
        };
    },

    computed: {
        isAppUrlReachable() {
            return Shopware.Store.get('context').app.config.settings.appUrlReachable;
        },

        hasAppsThatRequireAppUrl() {
            return Shopware.Store.get('context').app.config.settings.appsRequireAppUrl;
        },

        display() {
            return !this.isAppUrlReachable && this.hasAppsThatRequireAppUrl && !this.wasModalAlreadyShown;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    created() {
        if (!this.display && !this.isAppUrlReachable) {
            this.createAlertNotification();
        }

        if (this.isAppUrlReachable) {
            localStorage.removeItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN);
            this.removeAlertNotification();
        }
    },

    methods: {
        closeModal() {
            localStorage.setItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN, 'true');
            this.wasModalAlreadyShown = true;
            this.createAlertNotification();

            this.$emit('modal-close');
        },

        createAlertNotification() {
            this.createSystemNotificationInfo(this.notification);
        },

        removeAlertNotification() {
            Shopware.Store.get('notification').removeNotification(this.notification);
        },
    },
});
