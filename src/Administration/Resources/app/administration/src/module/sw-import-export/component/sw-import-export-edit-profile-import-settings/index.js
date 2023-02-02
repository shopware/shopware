import template from './sw-import-export-edit-profile-import-settings.html.twig';
import './sw-import-export-edit-profile-import-settings.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-import-export-edit-profile-import-settings', {
    template,

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    methods: {
        /**
         * makes sure that either one of the switches is enabled.
         * @param {boolean} newValue
         * @param {string} property
         */
        onChange(newValue, property) {
            if (newValue === false) {
                this.profile.config[property] = true;
            }
        },
    },
});
