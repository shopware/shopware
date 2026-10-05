/**
 * @sw-package discovery
 */
import template from './sw-settings-agentic-commerce.html.twig';
import './sw-settings-agentic-commerce.scss';

const { Mixin } = Shopware;

const EXTENSION_NAME = 'SwagAgenticCommerce';

interface ReadinessStep {
    key: string;
    title: string;
    description: string;
    complete: boolean;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['shopwareExtensionService', 'extensionStoreActionService', 'cacheApiService'],

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
                    description: this.$t('sw-settings-agentic-commerce.readiness.steps.extension.description'),
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
    },

    methods: {
        async onInstallExtension(): Promise<void> {
            this.isInstallingExtension = true;

            try {
                const extension = await this.findExtension();

                if (extension?.source === 'store') {
                    await this.extensionStoreActionService.downloadExtension(EXTENSION_NAME);
                }

                await this.shopwareExtensionService.installAndActivateExtension(EXTENSION_NAME, extension?.type ?? 'plugin');

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

        async findExtension() {
            const extensions = await this.extensionStoreActionService.getMyExtensions();

            return extensions.find((extension) => extension.name === EXTENSION_NAME) ?? null;
        },

        reloadPage(): void {
            window.location.reload();
        },

        onPrepareSalesChannels() {
            // to be overriden
        },
    },
});
