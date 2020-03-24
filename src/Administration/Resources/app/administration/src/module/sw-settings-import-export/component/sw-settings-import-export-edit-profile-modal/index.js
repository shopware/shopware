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
            supportedEntities: [
                { value: 'product', label: this.$tc('sw-settings-import-export.profile.productLabel') },
                { value: 'customer', label: this.$tc('sw-settings-import-export.profile.customerLabel') },
                { value: 'categories', label: this.$tc('sw-settings-import-export.profile.categoriesLabel') },
                { value: 'media', label: this.$tc('sw-settings-import-export.profile.mediaLabel') },
                { value: 'newsletter_recipient', label: this.$tc('sw-settings-import-export.profile.newsletterRecipientLabel') }
            ],
            supportedDelimiter: [
                { value: '^', label: this.$tc('sw-settings-import-export.profile.caretsLabel') },
                { value: ',', label: this.$tc('sw-settings-import-export.profile.commasLabel') },
                { value: '|', label: this.$tc('sw-settings-import-export.profile.pipesLabel') },
                { value: ';', label: this.$tc('sw-settings-import-export.profile.semicolonLabel') }
            ],
            supportedEnclosures: [
                { value: '"', label: this.$tc('sw-settings-import-export.profile.doubleQuoteLabel') }
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
            return this.isNew ? this.$tc('sw-settings-import-export.profile.newProfileLabel') : this.$tc('sw-settings-import-export.profile.editProfileLabel');
        },

        saveLabelSnippet() {
            return this.isNew ? this.$tc('sw-settings-import-export.profile.addProfileLabel') : this.$tc('sw-settings-import-export.profile.saveProfileLabel');
        }
    },

    created() {},

    methods: {}
});
