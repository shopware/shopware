import { mapState } from 'pinia';
import useSession from 'src/app/composables/use-session';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import grantPermissionsCardBackground from
        // eslint-disable-next-line import/no-unresolved
    '../../component/sw-settings-services-grant-permissions-modal/assets/grant-permissions-background.svg?no-inline';

import type { ServiceDescription } from '../../service/shopware-services.service';
import extractErrorMessage from '../../composables/extract-error';

type SwSettingsPageData = {
    grantPermissionsCardBackground: string,
    services: ServiceDescription[]
    suspended: boolean,
    loadingError: string,
};

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-index',

    template,

    inject: ['acl'],

    data(): SwSettingsPageData {
        return {
            grantPermissionsCardBackground,
            services: [],
            suspended: true,
            loadingError: '',
        };
    },

    computed: {
        ...mapState(useShopwareServicesStore, ['config', 'currentRevision', 'consentGiven']),
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
        ]).then(() => {
            this.suspended = false;
        }).catch(() =>  {});
    },

    methods: {
        activateServices() {
            console.log('activate services...');
        },

        async reloadServices() {
            try {
                const shopwareServicesService = Shopware.Service('shopwareServicesService');

                this.services = await shopwareServicesService.getInstalledServices();
            } catch(exception) {
                this.loadingError = extractErrorMessage(exception);

                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-services.exception.service-list'),
                });

                this.services = [];
            }
        },
    },
});
