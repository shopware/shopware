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
         * Makes sure that either one of the switches is enabled
         * and that it is possible to enable both fields again.
         *
         * @param {boolean} newValue
         * @param {string} property
         */
        onChange(newValue, property) {
            if (property === 'createEntities') {
                this.profile.config.createEntities = newValue;
                this.handleUpdateEntities(newValue);
            }

            if (property === 'updateEntities') {
                this.profile.config.updateEntities = newValue;
                this.handleCreateEntities(newValue);
            }
        },

        /**
         * @param {boolean} newUpdateEntitiesValue
         */
        handleCreateEntities(newUpdateEntitiesValue) {
            if (newUpdateEntitiesValue) {
                return;
            }

            this.profile.config.createEntities = true;
        },

        /**
         * @param {boolean} newCreateEntitiesValue
         */
        handleUpdateEntities(newCreateEntitiesValue) {
            if (newCreateEntitiesValue) {
                return;
            }

            this.profile.config.updateEntities = true;
        },
    },
};
