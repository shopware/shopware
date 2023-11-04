/**
 * @package system-settings
 */
import unionBy from 'lodash/unionBy';

import template from './sw-import-export-new-profile-wizard-mapping-page.html.twig';
import './sw-import-export-new-profile-wizard-mapping-page.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        profile: {
            type: Object,
            required: true,
        },
        systemRequiredFields: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            automatedCount: 0,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.automatedCount = this.countAutomatedValues();
            this.mergeMappings();

            this.$emit('next-allow');
        },

        mergeMappings() {
            const requiredMappings = Object.entries(this.systemRequiredFields).map(mapping => {
                const [key, mappedKey] = mapping;

                return { key, mappedKey };
            });

            const unifiedMappings = unionBy(this.profile.mapping, requiredMappings, 'key');
            unifiedMappings.sort((firstMappings, secondMappings) => {
                return firstMappings.position - secondMappings.position;
            });

            this.profile.mapping = unifiedMappings.map((mapping, index) => {
                if (!mapping.position) {
                    mapping.position = index;
                }

                return mapping;
            });
        },

        updateMapping(newMapping) {
            this.profile.mapping = newMapping;
        },

        countAutomatedValues() {
            return this.profile.mapping.reduce((count, mapping) => {
                if (mapping.key !== undefined && mapping.key !== null && mapping.key !== '') {
                    count += 1;
                }

                return count;
            }, 0);
        },
    },
};
