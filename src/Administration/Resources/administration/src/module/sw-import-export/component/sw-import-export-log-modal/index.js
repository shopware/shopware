import { Component } from 'src/core/shopware';
import template from './sw-import-export-log-modal.html.twig';
import './sw-import-export-log-modal.scss';

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
