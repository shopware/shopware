import { mapState } from 'pinia';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import grantPermissionsCardBackground from
    '../../component/sw-settings-services-grant-permissions-modal/assets/grant-permissions-background.png';

import type { ServiceDescription } from '../../service/shopware-services.service';

type SwSettingsPageData = {
    grantPermissionsCardBackground: string,
    services: ServiceDescription[]
    suspended: boolean,
};

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-index',

    template,

    data(): SwSettingsPageData {
        return {
            grantPermissionsCardBackground,
            services: [],
            suspended: true,
        };
    },

    computed: {
        ...mapState(useShopwareServicesStore, ['consent', 'consentGiven', 'legalDocuments']),
        optionalServices() {
            return this.services.filter((service) => service.needsPermissions);
        },
        defaultServices() {
            return this.services.filter((service) => !service.needsPermissions);
        },
        areServicesDeactivated() {
            return this.services.some((service) => service.active === false);
        },
    },

    created() {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        const shopwareServicesStore = useShopwareServicesStore();

        Promise.all([
            shopwareServicesService.getInstalledServices().then((services) => {
                this.services = services;
            }),
            shopwareServicesService.getServicesContext().then((servicesConsent) => {
                shopwareServicesStore.consent = servicesConsent;
            }),
            shopwareServicesService.getLegalDocumentLinks().then((legalDocuments) => {
                shopwareServicesStore.legalDocuments = legalDocuments;
            }),
        ]).then(() => {
            this.suspended = false;
        }).catch(() =>  {});
    },

    methods: {
        grantPermissions() {
            console.log('granting permissions...');
        },

        activateServices() {
            console.log('activate services...');
        },
    },
});