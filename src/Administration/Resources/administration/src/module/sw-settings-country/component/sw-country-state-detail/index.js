import { Component } from 'src/core/shopware';
import template from './sw-country-state-detail.html.twig';

Component.register('sw-country-state-detail', {
    template,

    props: {
        countryState: {
            type: Object,
            required: true
        }
    },

    computed: {
        modalTitle() {
            if (this.countryState.isNew()) {
                return this.$tc('sw-country-state-detail.titleNew');
            }

            return this.$tc('sw-country-state-detail.titleEdit');
        }
    },

    methods: {
        onCancel() {
            this.$emit('cancel-attribute-edit', this.countryState);
        },
        onSave() {
            this.$emit('save-attribute-edit', this.countryState);
        }
    }
});
