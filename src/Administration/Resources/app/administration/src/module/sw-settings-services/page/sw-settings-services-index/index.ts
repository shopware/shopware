import { mapState } from 'pinia';
import useSession from 'src/app/composables/use-session';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import type { ServiceDescription, ServicesStatus } from '../../service/shopware-services.service';
import extractError from '../../composables/extract-error';
import {
    getServicesWithShopwareAccountRequirement,
    type ServiceWithShopwareAccountRequirement,
} from '../../requirements/index';

import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';
import SwSettingsServicesServiceCard from '../../component/sw-settings-services-service-card';

type SwSettingsPageData = {
    grantPermissionsCardBackground: string;
    services: ServiceDescription[];
    status: ServicesStatus | null;
    suspended: boolean;
    loadingError: string;
    installationPollingSince: number | null;
    installationPollTimeout: number | undefined;
    installationStalled: boolean;
};

const INSTALLATION_POLL_INTERVAL = 10_000;

const INSTALLATION_POLL_DURATION = 120_000;

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
            status: null,
            suspended: true,
            loadingError: '',
            installationPollingSince: null,
            installationPollTimeout: undefined,
            installationStalled: false,
        };
    },

    computed: {
        ...mapState(useShopwareServicesStore, ['config', 'currentRevision', 'consentGiven']),
        servicesWithAccountRequirement(): ServiceWithShopwareAccountRequirement[] {
            return getServicesWithShopwareAccountRequirement(this.services);
        },

        servicesEnabled(): boolean {
            return this.status?.enabled ?? true;
        },

        servicesAutoEnabled(): boolean {
            return this.status?.enableServices === 'auto';
        },

        pendingServices(): ServicesStatus['pending'] {
            return this.status?.pending ?? [];
        },

        installing(): boolean {
            if (!this.servicesEnabled || this.config?.disabled) {
                return false;
            }

            return this.services.length === 0 || this.pendingServices.length > 0;
        },
    },

    created() {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        const serviceRegistryClient = Shopware.Service('serviceRegistryClient');
        const shopwareServicesStore = useShopwareServicesStore();
        const sessionStore = useSession();

        Promise.all([
            this.reloadServices(),
            shopwareServicesService.getServicesStatus().then((status) => {
                this.status = status;
            }),
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
                this.startInstallationPolling();
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

    beforeUnmount() {
        this.stopInstallationPolling();
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

                await this.checkInstallationProgress();
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

        async checkInstallationProgress() {
            await this.refreshInstallationProgress();
            this.startInstallationPolling();
        },

        startInstallationPolling() {
            this.installationStalled = false;
            this.installationPollingSince = Date.now();
            this.scheduleInstallationPoll();
        },

        stopInstallationPolling() {
            window.clearTimeout(this.installationPollTimeout);
            this.installationPollingSince = null;
        },

        scheduleInstallationPoll() {
            window.clearTimeout(this.installationPollTimeout);

            if (this.installationPollingSince === null) {
                return;
            }

            if (!this.installing) {
                this.stopInstallationPolling();

                return;
            }

            if (Date.now() - this.installationPollingSince >= INSTALLATION_POLL_DURATION) {
                this.stopInstallationPolling();
                this.installationStalled = true;

                return;
            }

            this.installationPollTimeout = window.setTimeout(() => {
                void this.refreshInstallationProgress().then(() => this.scheduleInstallationPoll());
            }, INSTALLATION_POLL_INTERVAL);
        },

        async refreshInstallationProgress() {
            const shopwareServicesService = Shopware.Service('shopwareServicesService');

            try {
                [this.services, this.status] = await Promise.all([
                    shopwareServicesService.getInstalledServices(),
                    shopwareServicesService.getServicesStatus(),
                ]);
                this.loadingError = '';
            } catch (exception) {
                this.loadingError = extractError(exception);
            }
        },
    },
});
