/**
 * @sw-package fundamentals@after-sales
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
            if (property === 'createEntities') {
                this.profile.config.createEntities = newValue;
                this.profile.config.updateEntities = !newValue;
            }

            if (property === 'updateEntities') {
                this.profile.config.createEntities = !newValue;
                this.profile.config.updateEntities = newValue;
            }
        },
    },
};
