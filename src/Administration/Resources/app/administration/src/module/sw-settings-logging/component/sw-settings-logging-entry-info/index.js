import template from './sw-settings-logging-entry-info.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-logging-entry-info', {
    template,

    props: {
        logEntry: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'raw',
        };
    },

    computed: {
        displayString() {
            return JSON.stringify(this.logEntry.context, null, 2);
        },
    },

    methods: {

        onClose() {
            this.$emit('close');
        },
    },
});
