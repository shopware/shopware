import { mapState } from 'pinia';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import type { ServiceDescription } from '../../service/shopware-services.service';

type SwSettingsPageData = {
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
});