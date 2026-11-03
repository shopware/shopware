import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import {
    aclService,
    defineAsyncComponent,
    extractError,
    getAssetFilter,
    getServiceRegistryClient,
    getServicesWithShopwareAccountRequirement,
    getShopwareServicesService,
    mapState,
    SwSettingsServicesDeactivateModal,
    SwSettingsServicesGrantPermissionsCard,
    SwSettingsServicesHero,
    SwSettingsServicesRevokePermissionsModal,
    useNotificationStore,
    useSession,
    useShopwareServicesStore,
    wrapComponentConfig,
    type ServiceDescription,
    type ServiceWithShopwareAccountRequirement,
} from '../dependencies';
import SwSettingsServicesServiceCard from './sw-settings-services-service-card/private';

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
export default wrapComponentConfig({
    name: 'sw-settings-services-index',

    template,

    inject: [aclService],

    components: {
        SwSettingsServicesHero,
        SwSettingsServicesGrantPermissionsCard,
        SwSettingsServicesRevokePermissionsModal,
        SwSettingsServicesDeactivateModal,
        SwSettingsServicesServiceCard: defineAsyncComponent(SwSettingsServicesServiceCard.load),
    },

    data(): SwSettingsPageData {
        const assetFilter = getAssetFilter();

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
        ...mapState(useShopwareServicesStore, ['config', 'currentRevision', 'consentGiven']),
        servicesWithAccountRequirement(): ServiceWithShopwareAccountRequirement[] {
            return getServicesWithShopwareAccountRequirement(this.services);
        },
    },

    created() {
        const shopwareServicesService = getShopwareServicesService();
        const serviceRegistryClient = getServiceRegistryClient();
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

                useNotificationStore().createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: errorMessage,
                });
            });
    },

    methods: {
        async activateServices() {
            try {
                const shopwareServicesService = getShopwareServicesService();
                const shopwareServicesStore = useShopwareServicesStore();

                shopwareServicesStore.config = await shopwareServicesService.enableAllServices();

                useNotificationStore().createNotification({
                    title: this.$t('sw-settings-services.index.services-enabled'),
                    variant: 'positive',
                    message: this.$t('sw-settings-services.index.services-scheduled'),
                });
            } catch (exceptionResponse) {
                useNotificationStore().createNotification({
                    title: this.$t('global.default.error'),
                    variant: 'critical',
                    message: extractError(exceptionResponse),
                    autoClose: false,
                });
            }
        },

        async reloadServices() {
            try {
                const shopwareServicesService = getShopwareServicesService();

                this.services = await shopwareServicesService.getInstalledServices();
            } catch (exception) {
                this.loadingError = extractError(exception);

                useNotificationStore().createNotification({
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
