import template from './sw-settings-services-revoke-permissions-modal.html.twig';
import './sw-settings-services-revoke-permissions-modal.scss';
import { extractErrorMessage, revokePermissions, useNotificationStore, wrapComponentConfig } from '../dependencies';

/**
 * @sw-package framework
 * @private
 */
export default wrapComponentConfig({
    name: 'sw-settings-services-revoke-permissions-modal',
    template,

    emits: ['service-permissions-revoked'],

    props: {
        servicesWithAccountRequirement: {
            type: Array,
            default: () => [],
        },
    },

    data() {
        return {
            isLoading: false,
        };
    },

    methods: {
        async revokePermissions(close: () => void) {
            try {
                this.isLoading = true;

                await revokePermissions();
            } catch (exception) {
                useNotificationStore().createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
                close();
            }
        },
    },
});
