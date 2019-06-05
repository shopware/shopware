import { Component } from 'src/core/shopware';
import template from './sw-settings-logging-entry-info.html.twig';

Component.register('sw-settings-logging-entry-info', {
    template,

    props: {
        logEntry: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            activeTab: 'raw'
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        },

        onClose() {
            this.$emit('close');
        }
    }
});
