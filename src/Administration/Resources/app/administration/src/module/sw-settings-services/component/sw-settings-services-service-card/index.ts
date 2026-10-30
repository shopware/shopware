/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type {
    AppHealth,
    CategorizedPermissions,
    ServiceDescription,
    ServiceState,
} from '../../service/shopware-services.service';
import template from './sw-settings-services-service-card.html.twig';
import './sw-settings-services-service-card.scss';
import extractErrorMessage from '../../composables/extract-error';
import getHealthLevel, { type HealthLevel } from '../../composables/app-health';

const STATUS_BY_STATE: Record<ServiceState, { color: string; label: string }> = {
    active: {
        color: 'green',
        label: 'sw-settings-services.service-card.status-active',
    },
    pending_permissions: {
        color: 'orange',
        label: 'sw-settings-services.service-card.status-awaiting-permissions',
    },
    inactive: {
        color: 'red',
        label: 'sw-settings-services.service-card.status-inactive',
    },
};

const STATUS_BY_HEALTH: Record<HealthLevel, { color: string; label: string }> = {
    ERROR: {
        color: 'red',
        label: 'sw-settings-services.service-card.status-connection-problem',
    },
    WARNING: {
        color: 'orange',
        label: 'sw-settings-services.service-card.status-needs-attention',
    },
};

const STATUS_NO_RECENT_ACTIVITY = {
    color: 'orange',
    label: 'sw-settings-services.service-card.status-no-recent-activity',
};

// the health endpoint may add reasons later; those keep the level from the status but get a generic message
const KNOWN_HEALTH_REASONS = [
    'shop_id_mismatch',
    'registration_incomplete',
    'credentials_rejected',
    'delivery_failing',
    'webhooks_suspended',
    'no_app_activity',
];

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-service-card',

    template,

    props: {
        service: {
            required: true,
            type: Object as PropType<ServiceDescription>,
        },

        health: {
            required: false,
            type: Object as PropType<AppHealth | null>,
            default: null,
        },
    },

    data(): {
        showDeactivateModal: boolean;
        showPermissionsModal: boolean;
        showHealthModal: boolean;
        categorizedPermissions: CategorizedPermissions | null;
        isLoading: boolean;
    } {
        return {
            showDeactivateModal: false,
            showPermissionsModal: false,
            showHealthModal: false,
            categorizedPermissions: null,
            isLoading: false,
        };
    },

    computed: {
        icon() {
            if (this.service.icon) {
                return `data:image/png;base64, ${this.service.icon}`;
            }

            const assetFilter = Shopware.Filter.getByName('asset');

            return assetFilter('/administration/administration/static/img/services/extension-icon-placeholder.svg');
        },

        status() {
            if (!this.healthLevel) {
                return STATUS_BY_STATE[this.service.state] ?? STATUS_BY_STATE.inactive;
            }

            return this.health?.reason === 'no_app_activity' ? STATUS_NO_RECENT_ACTIVITY : STATUS_BY_HEALTH[this.healthLevel];
        },

        healthLevel(): HealthLevel | null {
            return getHealthLevel(this.service, this.health);
        },

        healthMessage(): string | null {
            if (!this.healthLevel || !this.health) {
                return null;
            }

            const reason = KNOWN_HEALTH_REASONS.includes(this.health.reason ?? '') ? this.health.reason : 'unknown';

            return this.$t(`sw-settings-services.service-card.health-reason.${reason}`, {
                date: this.health.lastAppActivityAt ? this.dateFilter(this.health.lastAppActivityAt) : '',
            });
        },

        healthDetails(): string | null {
            if (!this.healthLevel || !this.health) {
                return null;
            }

            const { lastAttemptAt, lastStatusCode } = this.health;
            const details = [
                lastAttemptAt
                    ? this.$t('sw-settings-services.service-card.health-last-attempt', { date: this.dateFilter(lastAttemptAt) })
                    : null,
                lastStatusCode !== null
                    ? this.$t('sw-settings-services.service-card.health-response-code', { code: lastStatusCode })
                    : null,
            ].filter((detail) => detail !== null);

            return details.length > 0
                ? this.$t('sw-settings-services.service-card.health-support-details', { details: details.join(' • ') })
                : null;
        },

        updatedAt() {
            return this.dateFilter(this.service.updated_at, {
                month: '2-digit',
                day: '2-digit',
                year: 'numeric',
                hour: undefined,
                minute: undefined,
                second: undefined,
            });
        },

        readableVersion() {
            return this.service.version.split('-')[0];
        },

        stateChangePermitted() {
            return this.service.state_change_permitted;
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        openDeactivateModal(toggleFloatingUi: () => void) {
            this.showDeactivateModal = true;
            toggleFloatingUi();
        },

        async setActive(active: boolean, toggleFloatingUi?: () => void) {
            this.isLoading = true;

            try {
                const servicesService = Shopware.Service('shopwareServicesService');

                if (active) {
                    await servicesService.activateService(this.service.name);
                } else {
                    await servicesService.deactivateService(this.service.name);
                }

                this._reloadPage();
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
            }

            if (toggleFloatingUi) {
                toggleFloatingUi();
            }
        },

        async openPermissionsModal(toggleFloatingUi: () => void) {
            try {
                if (this.categorizedPermissions === null) {
                    const servicesService = Shopware.Service('shopwareServicesService');

                    const { permissions } = await servicesService.getCategorizedPermissions(this.service.name);
                    this.categorizedPermissions = permissions;
                }

                this.showPermissionsModal = true;
            } catch (exception) {
                Shopware.Store.get('notification').createNotification({
                    variant: 'critical',
                    message: extractErrorMessage(exception),
                });
            } finally {
                toggleFloatingUi();
            }
        },
    },
});
