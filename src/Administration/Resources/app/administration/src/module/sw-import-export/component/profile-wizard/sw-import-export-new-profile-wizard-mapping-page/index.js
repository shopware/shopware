import template from './sw-import-export-new-profile-wizard-mapping-page.html.twig';
import './sw-import-export-new-profile-wizard-mapping-page.scss';

Shopware.Component.register('sw-import-export-new-profile-wizard-mapping-page', {
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
            this.$emit('next-allow');
            this.automatedCount = this.profile.mapping.reduce((count, mapping) => {
                if (mapping.key !== undefined && mapping.key !== null && mapping.key !== '') {
                    count += 1;
                }

                return count;
            }, 0);
        },
    },
});
