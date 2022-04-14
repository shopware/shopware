import template from './sw-settings-country-address-handling.html.twig';
import './sw-settings-country-address-handling.scss';

const { Component } = Shopware;

Component.register('sw-settings-country-address-handling', {
    template,

    inject: [
        'acl',
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            editorConfig: {
                enableBasicAutocompletion: true,
            },
        };
    },

    watch: {
        'country.checkAdvancedPostalCodePattern'(value) {
            if (!value) {
                return;
            }

            this.$set(this.country, 'checkPostalCodePattern', false);
        },

        'country.checkPostalCodePattern'(value) {
            if (!value) {
                return;
            }

            this.$set(this.country, 'checkAdvancedPostalCodePattern', false);
        },
    },
});
