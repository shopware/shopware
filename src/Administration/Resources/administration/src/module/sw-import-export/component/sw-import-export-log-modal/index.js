import template from './sw-import-export-log-modal.html.twig';
import './sw-import-export-log-modal.scss';

const { Component } = Shopware;

Component.register('sw-import-export-log-modal', {
    template,

    props: {
        item: {
            type: Object,
            required: true
        }
    },

    methods: {
        translateFieldKey(field, key) {
            return this.$tc(`sw-import-export-log.general.enum.${field}.${key}`);
        }
    }
});
