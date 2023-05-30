/**
 * @package system-settings
 */
import template from './sw-import-export-edit-profile-import-settings.html.twig';
import './sw-import-export-edit-profile-import-settings.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
};
