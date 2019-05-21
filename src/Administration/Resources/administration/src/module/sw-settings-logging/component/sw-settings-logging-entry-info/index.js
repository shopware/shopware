import { Component } from 'src/core/shopware';
import template from './sw-settings-logging-entry-info.html.twig';

Component.register('sw-settings-logging-entry-info', {
    template,

    props: {
        eventData: {
            type: Object,
            required: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {

        }
    }
});
