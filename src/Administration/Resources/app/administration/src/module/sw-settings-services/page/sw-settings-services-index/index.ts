import { mapState } from 'pinia';
import useSession from 'src/app/composables/use-session';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import type { ServiceDescription } from '../../service/shopware-services.service';
import extractError from '../../composables/extract-error';

import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';
import SwSettingsServicesServiceCard from '../../component/sw-settings-services-service-card';

type SwSettingsPageData = {
    grantPermissionsCardBackground: string;
    services: ServiceDescription[];
    suspended: boolean;
    loadingError: string;
};

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-index',

    template,

    inject: ['acl'],

    components: {
        SwSettingsServicesHero,
        SwSettingsServicesGrantPermissionsCard,
        SwSettingsServicesRevokePermissionsModal,
        SwSettingsServicesDeactivateModal,
        SwSettingsServicesServiceCard,
    },

    data(): SwSettingsPageData {
        const assetFilter = Shopware.Filter.getByName('asset');

        return {
            grantPermissionsCardBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            services: [],
            suspended: true,
            loadingError: '',
        };
    },

    computed: {
        ...mapState(useShopwareServicesStore, [
            'config',
            'currentRevision',
            'consentGiven',
        ]),
    },

    created() {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        const serviceRegistryClient = Shopware.Service('serviceRegistryClient');
        const shopwareServicesStore = useShopwareServicesStore();
        const sessionStore = useSession();

        Promise.all([
            this.reloadServices(),
            shopwareServicesService.getServicesContext().then((servicesConsent) => {
                shopwareServicesStore.config = servicesConsent;
            }),
            serviceRegistryClient
                .getCurrentRevision(sessionStore.currentLocale.value ?? 'en-GB')
                .then((serviceRevisions) => {
                    shopwareServicesStore.revisions = serviceRevisions;
                }),
        ])
            .then(() => {
                this.suspended = false;
            })
            .catch((exception) => {
                const errorMessage = extractError(exception);

                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: errorMessage,
                });
            });
    },

    methods: {
        async activateServices() {
            try {
                const shopwareServicesService = Shopware.Service('shopwareServicesService');
                const shopwareServicesStore = useShopwareServicesStore();

                shopwareServicesStore.config = await shopwareServicesService.enableAllServices();

                Shopware.Store.get('notification').createNotification({
                    title: this.$t('sw-settings-services.index.services-enabled'),
                    variant: 'positive',
                    message: this.$t('sw-settings-services.index.services-scheduled'),
                });
            } catch (exceptionResponse) {
                Shopware.Store.get('notification').createNotification({
                    title: this.$t('global.default.error'),
                    variant: 'critical',
                    message: extractError(exceptionResponse),
                    autoClose: false,
                });
            }
        },

        async reloadServices() {
            try {
                const shopwareServicesService = Shopware.Service('shopwareServicesService');

                this.services = await shopwareServicesService.getInstalledServices();
            } catch (exception) {
                this.loadingError = extractError(exception);

                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-services.exception.service-list'),
                    autoClose: false,
                });

                this.services = [];
            }
        },
    },
});
