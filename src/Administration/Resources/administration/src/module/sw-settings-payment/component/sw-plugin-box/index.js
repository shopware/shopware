import { Component, State } from 'src/core/shopware';
import template from './sw-plugin-box.html.twig';
import './sw-plugin-box.scss';

Component.register('sw-plugin-box', {
    template,

    props: {
        pluginId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            plugin: {}
        };
    },

    computed: {
        pluginStore() {
            return State.getStore('plugin');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.plugin = this.pluginStore.getById(this.pluginId);
        }
    }
});
