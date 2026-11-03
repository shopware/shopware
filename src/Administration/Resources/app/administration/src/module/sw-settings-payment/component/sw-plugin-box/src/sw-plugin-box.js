import template from './sw-plugin-box.html.twig';
import './sw-plugin-box.scss';
import { repositoryFactoryService, systemConfigApiService } from '../dependencies';

/**
 * @sw-package checkout
 *
 * @public
 */
export default {
    template,

    inject: [systemConfigApiService, repositoryFactoryService],

    props: {
        pluginId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            plugin: {},
            hasPluginConfig: false,
        };
    },

    computed: {
        pluginRepository() {
            return this.repositoryFactory.create('plugin');
        },
    },

    watch: {
        'plugin.name': {
            handler() {
                if (!this.plugin.name || this.hasPluginConfig) {
                    return;
                }

                this.checkPluginConfig();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.pluginRepository.get(this.pluginId).then((plugin) => {
                this.plugin = plugin;
            });
        },

        checkPluginConfig() {
            this.systemConfigApiService
                .checkConfig(`${this.plugin.name}.config`)
                .then((response) => {
                    this.hasPluginConfig = response;
                })
                .catch(() => {
                    this.hasPluginConfig = false;
                });
        },
    },
};
