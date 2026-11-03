/**
 * @sw-package framework
 */

import template from './sw-app-wrong-app-url-modal.html.twig';
import './sw-app-wrong-app-url-modal.scss';
import {
    copyStringToClipboard,
    getAssetFilter,
    notificationMixin,
    useContextStore,
    useNotificationStore,
} from '../dependencies';

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
            verifyCommand: 'bin/console app:url:verify',
            commandCopied: false,
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

        appUrl() {
            return useContextStore().app.config.appUrl;
        },

        appUrlStatusCode() {
            return useContextStore().app.config.settings?.appUrlStatusCode;
        },

        hasAppsThatRequireAppUrl() {
            return useContextStore().app.config.settings?.appsRequireAppUrl;
        },

        display() {
            return !this.isAppUrlReachable && this.hasAppsThatRequireAppUrl && !this.wasModalAlreadyShown;
        },

        assetFilter() {
            return getAssetFilter();
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
        async copyVerifyCommand() {
            await copyStringToClipboard(this.verifyCommand);
            this.commandCopied = true;
        },

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
