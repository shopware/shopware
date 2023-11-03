import type { PropType } from 'vue';
import type { ExtensionType } from 'src/module/sw-extension/service/extension-store-action.service';
import template from './sw-plugin-card.html.twig';
import './sw-plugin-card.scss';

type ComponentData = {
    pluginIsLoading: boolean,
    pluginIsSaveSuccessful: boolean,
}

type RecommendedPlugin = {
    active: boolean,
    name: string,
    iconPath: string,
    label: string,
    manufacturer: string,
    shortDescription: string,
    type: ExtensionType,
}

/**
 * @package services-settings
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'cacheApiService',
        'extensionHelperService',
        'shopwareExtensionService',
    ],

    mixins: [Shopware.Mixin.getByName('sw-extension-error')],

    props: {
        plugin: {
            type: Object as PropType<RecommendedPlugin>,
            required: true,
        },
        showDescription: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },
    },

    data(): ComponentData {
        return {
            pluginIsLoading: false,
            pluginIsSaveSuccessful: false,
        };
    },

    computed: {
        pluginIsNotActive(): boolean {
            return !this.plugin.active;
        },

        truncateFilter() {
            return Shopware.Filter.getByName('truncate');
        },
    },

    methods: {
        onInstall(): void {
            void this.setupPlugin();
        },

        /**
         * @deprecated tag:v6.6.0 - Will emit hypernated event only.
         */
        async setupPlugin(): Promise<void> {
            this.pluginIsLoading = true;
            this.pluginIsSaveSuccessful = false;

            try {
                await this.extensionHelperService.downloadAndActivateExtension(this.plugin.name, this.plugin.type);
                this.pluginIsSaveSuccessful = true;
                this.$emit('extension-activated');
            } catch (error: unknown) {
                // ts can not recognize functions from mixins
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.showExtensionErrors(error);
            } finally {
                this.pluginIsLoading = false;

                if (this.plugin.type === 'plugin') {
                    // wait until cacheApiService is transpiled to ts
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-call
                    this.cacheApiService.clear();
                }

                await this.shopwareExtensionService.updateExtensionData();

                this.$emit('on-plugin-installed', this.plugin.name);
                this.$emit('onPluginInstalled', this.plugin.name);
            }
        },
    },
});
