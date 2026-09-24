/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import type { CategorizedPermissions, ServiceDescription, ServiceState } from '../../service/shopware-services.service';
import template from './sw-settings-services-service-card.html.twig';
import './sw-settings-services-service-card.scss';
import extractErrorMessage from '../../composables/extract-error';

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
    },

    data(): {
        showDeactivateModal: boolean;
        showPermissionsModal: boolean;
        categorizedPermissions: CategorizedPermissions | null;
        isLoading: boolean;
    } {
        return {
            showDeactivateModal: false,
            showPermissionsModal: false,
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
            return STATUS_BY_STATE[this.service.state] ?? STATUS_BY_STATE.inactive;
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
