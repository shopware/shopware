import template from './sw-plugin-card.html.twig';
import './sw-plugin-card.scss';
import {
    cacheApiService,
    extensionHelperService,
    type ExtensionType,
    getTruncateFilter,
    shopwareExtensionService,
    swExtensionErrorMixin,
    wrapComponentConfig,
} from '../dependencies';

type ComponentData = {
    pluginIsLoading: boolean;
};

type RecommendedPlugin = {
    active: boolean;
    name: string;
    iconPath: string;
    label: string;
    manufacturer: string;
    shortDescription: string;
    type: ExtensionType;
};

/**
 * @sw-package fundamentals@after-sales
 *
 * @private
 */
export default wrapComponentConfig({
    template,

    inject: [cacheApiService, extensionHelperService, shopwareExtensionService],

    emits: ['extension-activated', 'on-plugin-installed'],

    mixins: [swExtensionErrorMixin],

    props: {
        plugin: {
            type: Object as PropType<RecommendedPlugin>,
            required: true,
        },
        showDescription: {
            type: Boolean,
            default: true,
            required: false,
        },
    },

    data(): ComponentData {
        return {
            pluginIsLoading: false,
        };
    },

    computed: {
        pluginIsNotActive(): boolean {
            return !this.plugin.active;
        },

        truncateFilter() {
            return getTruncateFilter();
        },
    },

    methods: {
        onInstall(): void {
            void this.setupPlugin();
        },

        async setupPlugin(): Promise<void> {
            this.pluginIsLoading = true;

            try {
                await this.extensionHelperService.downloadAndActivateExtension(this.plugin.name, this.plugin.type);
                this.$emit('extension-activated');
            } catch (error: unknown) {
                this.showExtensionErrors(error);
            } finally {
                this.pluginIsLoading = false;

                if (this.plugin.type === 'plugin') {
                    // wait until cacheApiService is transpiled to ts
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.cacheApiService.clear();
                }

                await this.shopwareExtensionService.updateExtensionData();

                this.$emit('on-plugin-installed', this.plugin.name);
            }
        },
    },
});
