import { Component, State } from 'src/core/shopware';
import template from './sw-plugin-box.html.twig';
import './sw-plugin-box.scss';

Component.register('sw-plugin-box', {
    template,

    inject: ['systemConfigApiService'],

    props: {
        pluginId: {
            type: String,
            required: true
        }
    },


    data() {
        return {
            plugin: {},
            hasPluginConfig: false
        };
    },

    computed: {
        pluginStore() {
            return State.getStore('plugin');
        }
    },

    watch: {
        'plugin.name': {
            handler() {
                if (!this.plugin.name || this.hasPluginConfig) {
                    return;
                }

                this.checkPluginConfig();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.plugin = this.pluginStore.getById(this.pluginId);
        },

        checkPluginConfig() {
            // TODO: problem with check for config since 25.04.19 => have to wait for solution
            // const domain = this.systemConfigApiService.getDomainFromNamespace(this.plugin.name);
            //
            // this.systemConfigApiService.getConfig(domain).then(() => {
            //     this.hasPluginConfig = true;
            // });
        }
    }
});
