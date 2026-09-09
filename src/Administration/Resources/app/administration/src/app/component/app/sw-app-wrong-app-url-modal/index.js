/**
 * @sw-package framework
 */

import template from './sw-app-wrong-app-url-modal.html.twig';
import './sw-app-wrong-app-url-modal.scss';
import notificationMixin from 'shopware:mixins/notification';
import useContextStore from 'shopware:stores/context';
import useNotificationStore from 'shopware:stores/notification';

const STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN = 'sw-app-wrong-app-url-modal-shown';

/**
 * @private
 */
export default {
    template,

    emits: ['modal-close'],

    mixins: [notificationMixin],

    data() {
        return {
            wasModalAlreadyShown: !!localStorage.getItem(STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN),
            notification: {
                title: this.$t('sw-app.component.sw-app-wrong-app-url-modal.title'),
                message: this.$t('sw-app.component.sw-app-wrong-app-url-modal.explanation'),
                actions: [
                    {
                        label: this.$t('sw-app.component.sw-app-wrong-app-url-modal.labelLearnMoreButton'),
                        route: this.$t('sw-app.component.sw-app-wrong-app-url-modal.linkToDocsArticle'),
                    },
                ],
                uuid: STORAGE_KEY_WAS_WRONG_APP_MODAL_SHOWN,
            },
        };
    },

    computed: {
        isAppUrlReachable() {
            return useContextStore().app.config.settings?.appUrlReachable;
        },

        hasAppsThatRequireAppUrl() {
            return useContextStore().app.config.settings?.appsRequireAppUrl;
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
            useNotificationStore().removeNotification(this.notification);
        },
    },
};
