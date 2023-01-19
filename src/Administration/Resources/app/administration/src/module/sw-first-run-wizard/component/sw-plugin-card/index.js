import template from './sw-plugin-card.html.twig';
import './sw-plugin-card.scss';

/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['cacheApiService', 'extensionHelperService'],

    mixins: ['sw-extension-error'],

    props: {
        plugin: {
            type: Object,
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

    data() {
        return {
            pluginIsLoading: false,
            pluginIsSaveSuccessful: false,
        };
    },

    computed: {
        pluginIsNotActive() {
            return !this.plugin.active;
        },
    },

    methods: {
        onInstall() {
            this.setupPlugin();
        },

        setupPlugin() {
            const pluginName = this.plugin.name;

            this.pluginIsLoading = true;
            this.pluginIsSaveSuccessful = false;

            return this.extensionHelperService.downloadAndActivateExtension(pluginName)
                .then(() => {
                    this.pluginIsSaveSuccessful = true;
                    this.$emit('extension-activated');
                })
                .catch(error => {
                    this.showExtensionErrors(error);
                })
                .finally(() => {
                    this.pluginIsLoading = false;
                    this.cacheApiService.clear();

                    this.$emit('onPluginInstalled', pluginName);
                });
        },
    },
};
