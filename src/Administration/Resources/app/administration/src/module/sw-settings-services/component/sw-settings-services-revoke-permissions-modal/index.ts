import template from './sw-settings-services-revoke-permissions-modal.html.twig';
import './sw-settings-services-revoke-permissions-modal.scss';
import { revokePermissions } from '../../composables/permissions';
import extractErrorMessage from '../../composables/extract-error';
import SwSettingsServicesGmvInfo from '../sw-settings-services-gmv-info';
import { GMV_REPORTING_SERVICE_NAME } from '../../requirements/index';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-revoke-permissions-modal',
    template,

    components: {
        SwSettingsServicesGmvInfo,
    },

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

    computed: {
        gmvReportingServiceName() {
            return GMV_REPORTING_SERVICE_NAME;
        },
    },

    methods: {
        async revokePermissions(close: () => void) {
            try {
                this.isLoading = true;

                await revokePermissions();
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
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
