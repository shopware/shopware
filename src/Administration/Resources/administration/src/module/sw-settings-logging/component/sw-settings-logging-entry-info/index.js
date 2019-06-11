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

    computed: {
        displayString() {
            return JSON.stringify(this.logEntry.context, null, 2);
        }
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
