/**
 * @sw-package discovery
 */
import template from './sw-settings-agentic-commerce.html.twig';
import './sw-settings-agentic-commerce.scss';

const { Mixin } = Shopware;

const EXTENSION_NAME = 'SwagAgenticCommerce';

// Agentic Commerce's product id in the Shopware Store, used to deep-link merchants who do
// not own the extension yet to its Store listing.
const STORE_PRODUCT_ID = '21761';

interface ReadinessStep {
    key: string;
    title: string;
    description: string;
    complete: boolean;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'shopwareExtensionService',
        'extensionStoreActionService',
        'cacheApiService',
        'acl',
    ],

    mixins: [Mixin.getByName('notification')],

    data(): {
        isInstallingExtension: boolean;
        preparedSalesChannelCount: number;
        agenticSalesChannelCount: number;
    } {
        return {
            isInstallingExtension: false,
            preparedSalesChannelCount: 0,
            agenticSalesChannelCount: 0,
        };
    },

    computed: {
        // Core renders the "extension not installed yet" state. The SwagAgenticCommerce
        // plugin overrides the state-specific pieces below (step completion, descriptions,
        // subtitle, extension status) once it is installed and active.
        steps(): ReadinessStep[] {
            return [
                {
                    key: 'extension',
                    title: this.$t('sw-settings-agentic-commerce.readiness.steps.extension.title'),
                    description: this.isExtensionInstalled
                        ? this.$t('sw-settings-agentic-commerce.readiness.steps.extension.updateDescription')
                        : this.$t('sw-settings-agentic-commerce.readiness.steps.extension.description'),
                    complete: false,
                },
                {
                    key: 'prepareSalesChannels',
                    title: this.$t('sw-settings-agentic-commerce.readiness.steps.prepareSalesChannels.title'),
                    description: this.$t('sw-settings-agentic-commerce.readiness.steps.prepareSalesChannels.description'),
                    complete: false,
                },
            ];
        },

        canPrepareSalesChannels(): boolean {
            return false;
        },

        completedStepCount(): number {
            return this.steps.filter((step) => step.complete).length;
        },

        totalStepCount(): number {
            return this.steps.length;
        },

        progressValue(): number {
            return Math.round((this.completedStepCount / this.totalStepCount) * 100);
        },

        isReady(): boolean {
            return this.completedStepCount === this.totalStepCount;
        },

        readinessSubtitle(): string {
            return this.$t('sw-settings-agentic-commerce.readiness.subtitleInactive');
        },

        extensionStatusLabel(): string {
            return this.$t('sw-settings-agentic-commerce.shopReadiness.extensionNotInstalled');
        },

        isExtensionInstalled(): boolean {
            return !!Shopware.Context.app.config.bundles?.SwagAgenticCommerce;
        },

        extensionActionLabel(): string {
            return this.isExtensionInstalled
                ? this.$t('sw-settings-agentic-commerce.readiness.steps.extension.updateAction')
                : this.$t('sw-settings-agentic-commerce.readiness.steps.extension.action');
        },

        canInstallExtension(): boolean {
            return this.acl.can('system.plugin_maintain');
        },

        // Generic, overridable tooltip for the prepare button. Off by default; the
        // SwagAgenticCommerce plugin fills it in to explain a missing ucp.editor privilege.
        prepareSalesChannelsTooltip(): { message: string; disabled: boolean; showOnDisabledElements: boolean } {
            return { message: '', disabled: true, showOnDisabledElements: true };
        },
    },

    methods: {
        onExtensionAction(): Promise<void> {
            return this.isExtensionInstalled ? this.onUpdateExtension() : this.onInstallExtension();
        },

        async onInstallExtension(): Promise<void> {
            this.isInstallingExtension = true;

            try {
                const extension = await this.findExtension();

                // Not owned/licensed and not on disk: it cannot be installed directly, so send
                // the merchant to the Store listing to acquire it instead of failing.
                if (!extension) {
                    this.openExtensionInStore();
                    return;
                }

                if (extension.source === 'store') {
                    await this.extensionStoreActionService.downloadExtension(EXTENSION_NAME);
                }

                await this.shopwareExtensionService.installAndActivateExtension(EXTENSION_NAME, extension.type ?? 'plugin');

                // wait until cacheApiService is transpiled to ts
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                await this.cacheApiService.clear();
                this.reloadPage();
            } catch {
                this.isInstallingExtension = false;
                this.createNotificationError({
                    message: this.$t('sw-settings-agentic-commerce.readiness.steps.extension.installError'),
                });
            }
        },

        async onUpdateExtension(): Promise<void> {
            this.isInstallingExtension = true;

            try {
                const extension = await this.findExtension();

                // No longer owned/available: fall back to the Store listing.
                if (!extension) {
                    this.openExtensionInStore();
                    return;
                }

                if (extension.source === 'store') {
                    await this.extensionStoreActionService.downloadExtension(EXTENSION_NAME);
                }

                await this.shopwareExtensionService.updateExtension(EXTENSION_NAME, extension.type ?? 'plugin');

                // wait until cacheApiService is transpiled to ts
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                await this.cacheApiService.clear();
                this.reloadPage();
            } catch {
                this.isInstallingExtension = false;
                this.createNotificationError({
                    message: this.$t('sw-settings-agentic-commerce.readiness.steps.extension.updateError'),
                });
            }
        },

        async findExtension() {
            const extensions = await this.extensionStoreActionService.getMyExtensions();

            return extensions.find((extension) => extension.name === EXTENSION_NAME) ?? null;
        },

        openExtensionInStore(): void {
            this.isInstallingExtension = false;

            if (this.$router.hasRoute('sw.extension.store.detail')) {
                void this.$router.push({ name: 'sw.extension.store.detail', params: { id: STORE_PRODUCT_ID } });
                return;
            }

            void this.$router.push({ name: 'sw.extension.store.landing-page' });
        },

        reloadPage(): void {
            window.location.reload();
        },

        onPrepareSalesChannels() {
            // to be overriden
        },
    },
});
