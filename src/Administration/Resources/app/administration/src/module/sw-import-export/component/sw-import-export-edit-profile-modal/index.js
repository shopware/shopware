import template from './sw-import-export-edit-profile-modal.html.twig';
import './sw-import-export-edit-profile-modal.scss';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @private
 */
Shopware.Component.register('sw-import-export-edit-profile-modal', {
    template,

    props: {
        profile: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        }
    },

    data() {
        return {
            supportedEntities: [
                {
                    value: 'product',
                    label: this.$tc('sw-import-export.profile.productLabel')
                },
                {
                    value: 'customer',
                    label: this.$tc('sw-import-export.profile.customerLabel')
                },
                {
                    value: 'category',
                    label: this.$tc('sw-import-export.profile.categoriesLabel')
                },
                {
                    value: 'media',
                    label: this.$tc('sw-import-export.profile.mediaLabel')
                },
                {
                    value: 'newsletter_recipient',
                    label: this.$tc('sw-import-export.profile.newsletterRecipientLabel')
                },
                {
                    value: 'property_group_option',
                    label: this.$tc('sw-import-export.profile.propertyLabel')
                },
                {
                    value: 'product_configurator_setting',
                    label: this.$tc('sw-import-export.profile.configuratorSettingLabel')
                }
            ],
            supportedDelimiter: [
                {
                    value: '^',
                    label: this.$tc('sw-import-export.profile.caretsLabel')
                },
                {
                    value: ',',
                    label: this.$tc('sw-import-export.profile.commasLabel')
                },
                {
                    value: ';',
                    label: this.$tc('sw-import-export.profile.semicolonLabel')
                }
            ],
            supportedEnclosures: [
                {
                    value: '"',
                    label: this.$tc('sw-import-export.profile.doubleQuoteLabel')
                }
            ],
            missingRequiredFields: []
        };
    },

    computed: {
        ...mapPropertyErrors('profile',
            [
                'name',
                'sourceEntity',
                'delimiter',
                'enclosure'
            ]),

        isNew() {
            if (!this.profile || !this.profile.isNew) {
                return false;
            }

            return this.profile.isNew();
        },


        mappingLength() {
            if (!this.profile.mapping) {
                return 0;
            }

            return this.profile.mapping.length;
        },

        modalTitle() {
            return this.isNew ?
                this.$tc('sw-import-export.profile.newProfileLabel') :
                this.$tc('sw-import-export.profile.editProfileLabel');
        },

        saveLabelSnippet() {
            return this.isNew ?
                this.$tc('sw-import-export.profile.addProfileLabel') :
                this.$tc('sw-import-export.profile.saveProfileLabel');
        },

        showValidationError() {
            return this.missingRequiredFields.length > 0;
        }
    },

    methods: {
        saveProfile() {
            const validationErrors = Shopware.Service('importExportProfileMapping').validate(
                this.profile.sourceEntity,
                this.profile.mapping
            );

            if (validationErrors.missingRequiredFields.length > 0) {
                this.missingRequiredFields = validationErrors.missingRequiredFields;
                return;
            }

            this.$emit('profile-save');
        },

        resetViolations() {
            this.missingRequiredFields = [];
        }
    }
});
