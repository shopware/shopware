import template from './sw-settings-import-export-edit-profile-modal.html.twig';
import './sw-settings-import-export-edit-profile-modal.scss';

Shopware.Component.register('sw-settings-import-export-edit-profile-modal', {
    template,

    inject: [],

    props: {
        profile: {
            type: Object,
            required: false,
            default: false
        }
    },

    data() {
        return {
            // ToDo Snippets
            supportedEntities: [
                { value: 'product', label: 'Product' },
                { value: 'customer', label: 'Customer' },
                { value: 'categories', label: 'Categories' },
                { value: 'media', label: 'Media' },
                { value: 'newsletter_recipient', label: 'Newsletter recipient' }
            ],
            supportedDelimiter: [
                { value: '^', label: 'Carets (^)' },
                { value: ',', label: 'Commas (,)' },
                { value: '|', label: 'Pipes (|)' },
                { value: ';', label: 'Commas (;)' }
            ],
            supportedEnclosures: [
                { value: '"', label: 'Double quote' }
            ]
        };
    },

    computed: {
        isNew() {
            if (!this.profile || !this.profile.isNew) {
                return false;
            }

            return this.profile.isNew();
        },

        modalTitle() {
            return this.isNew ? 'New profile' : 'Edit profile';
        },

        saveLabelSnippet() {
            return this.isNew ? 'Add profile' : 'Save profile';
        }
    },

    created() {},

    methods: {}
});
