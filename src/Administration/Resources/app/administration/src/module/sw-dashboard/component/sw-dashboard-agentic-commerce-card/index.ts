import template from './sw-dashboard-agentic-commerce-card.html.twig';
import './sw-dashboard-agentic-commerce-card.scss';

import type { Extension } from 'src/module/sw-extension/service/extension-store-action.service';

/** @private */
export const AGENTIC_COMMERCE_EXTENSION_NAME = 'SwagAgenticCommerce';
/** @private */
export const HIDDEN_CONFIG_KEY = 'core.hide-agentic-commerce-dashboard-card';

type ComponentData = {
    extension: Extension | null;
    iconSource: string;
    isDismissed: boolean;
    isLoading: boolean;
    isDismissing: boolean;
    isInitialized: boolean;
};

/**
 * @sw-package discovery
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'shopwareExtensionService',
        'extensionStoreActionService',
        'cacheApiService',
    ],

    mixins: [Shopware.Mixin.getByName('notification')],

    data(): ComponentData {
        const assetFilter = Shopware.Filter.getByName('asset');

        return {
            extension: null,
            iconSource: assetFilter('/administration/administration/static/img/agentic-commerce/icon.png'),
            isDismissed: false,
            isLoading: false,
            isDismissing: false,
            isInitialized: false,
        };
    },

    computed: {
        canManageExtensions(): boolean {
            return (
                this.acl.can('system.plugin_maintain') &&
                !Shopware.Store.get('context').app.config.settings?.disableExtensionManagement
            );
        },

        isVisible(): boolean {
            return this.canManageExtensions && this.isInitialized && !this.isDismissed && !this.extension?.active;
        },
    },

    created() {
        void this.loadCardState();
    },

    methods: {
        async loadCardState(): Promise<void> {
            if (!this.canManageExtensions) {
                return;
            }

            try {
                const [configResponse, extensions] = await Promise.all([
                    Shopware.Service('userConfigService').search([HIDDEN_CONFIG_KEY]),
                    this.extensionStoreActionService.getMyExtensions(),
                ]);

                if (!configResponse) {
                    throw new Error('Could not load user configuration');
                }

                this.isDismissed = (configResponse.data?.[HIDDEN_CONFIG_KEY] as boolean[] | undefined)?.[0] ?? false;
                this.extension = extensions.find((extension) => extension.name === AGENTIC_COMMERCE_EXTENSION_NAME) ?? null;
                this.isInitialized = true;
            } catch {
                // Keep the error object out of the console because request details may contain API tokens.
                console.error('Failed to load Agentic Commerce dashboard card state.');
                // The dashboard card is optional; keep it hidden if its state cannot be loaded.
            }
        },

        async dismissCard(): Promise<void> {
            if (this.isDismissing || !this.isVisible) {
                return;
            }

            this.isDismissing = true;

            try {
                await Shopware.Service('userConfigService').upsert({
                    [HIDDEN_CONFIG_KEY]: [true],
                });

                this.isDismissed = true;
            } catch (error) {
                this.showOperationError(error);
            } finally {
                this.isDismissing = false;
            }
        },

        async installAgenticCommerce(): Promise<void> {
            if (this.isLoading || !this.canManageExtensions) {
                return;
            }

            this.isLoading = true;
            const cacheApiService = this.cacheApiService as { clear(): Promise<unknown> };

            try {
                const extensions = await this.extensionStoreActionService.getMyExtensions();
                const extension = extensions.find((entry) => entry.name === AGENTIC_COMMERCE_EXTENSION_NAME) ?? null;

                this.extension = extension;

                if (extension?.installedAt) {
                    if (!extension.active) {
                        await this.shopwareExtensionService.activateExtension(AGENTIC_COMMERCE_EXTENSION_NAME, 'plugin');
                    }
                } else {
                    if (!extension || extension.source === 'store') {
                        await this.shopwareExtensionService.checkLogin();

                        if (!Shopware.Store.get('shopwareExtensions').userInfo) {
                            this.showStoreLoginRequired();
                            return;
                        }

                        await this.extensionStoreActionService.downloadExtension(AGENTIC_COMMERCE_EXTENSION_NAME);
                    }

                    await this.shopwareExtensionService.installAndActivateExtension(
                        AGENTIC_COMMERCE_EXTENSION_NAME,
                        'plugin',
                    );
                }

                await cacheApiService.clear();
                this.reloadPage();
            } catch (error) {
                this.showOperationError(error);
                await this.refreshExtensionState();
            } finally {
                this.isLoading = false;
            }
        },

        async refreshExtensionState(): Promise<void> {
            try {
                const extensions = await this.extensionStoreActionService.getMyExtensions();
                this.extension = extensions.find((extension) => extension.name === AGENTIC_COMMERCE_EXTENSION_NAME) ?? null;
                this.isInitialized = true;
            } catch {
                // Keep the error object out of the console because request details may contain API tokens.
                console.error('Failed to refresh Agentic Commerce extension state.');
                // Keep the existing state; the next user action will retry the lookup.
            }
        },

        showOperationError(error: unknown): void {
            const extensionErrorService = Shopware.Service('extensionErrorService');
            const notifications = extensionErrorService?.handleErrorResponse(error, this) ?? [];

            if (notifications.length > 0) {
                notifications.forEach((notification) => this.createNotificationError(notification));
                return;
            }

            this.createNotificationError({
                message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
            });
        },

        showStoreLoginRequired(): void {
            this.createNotificationError({
                title: this.$t('sw-dashboard.agenticCommerceCard.storeLoginRequired.title'),
                message: this.$t('sw-dashboard.agenticCommerceCard.storeLoginRequired.message'),
                autoClose: false,
                actions: [
                    {
                        label: this.$t('sw-dashboard.agenticCommerceCard.storeLoginRequired.login'),
                        method: () => {
                            void this.$router.push({ name: 'sw.extension.my-extensions.account' });
                        },
                    },
                ],
            });
        },

        reloadPage(): void {
            window.location.reload();
        },
    },
});
