import template from './sw-import-export-edit-profile-modal.html.twig';
import './sw-import-export-edit-profile-modal.scss';

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-edit-profile-modal', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        profile: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
    },

    data() {
        return {
            supportedEntities: [
                {
                    value: 'product',
                    label: this.$tc('sw-import-export.profile.productLabel'),
                },
                {
                    value: 'customer',
                    label: this.$tc('sw-import-export.profile.customerLabel'),
                },
                {
                    value: 'category',
                    label: this.$tc('sw-import-export.profile.categoriesLabel'),
                },
                {
                    value: 'media',
                    label: this.$tc('sw-import-export.profile.mediaLabel'),
                },
                {
                    value: 'newsletter_recipient',
                    label: this.$tc('sw-import-export.profile.newsletterRecipientLabel'),
                },
                {
                    value: 'property_group_option',
                    label: this.$tc('sw-import-export.profile.propertyLabel'),
                },
                {
                    value: 'product_configurator_setting',
                    label: this.$tc('sw-import-export.profile.configuratorSettingLabel'),
                },
                {
                    value: 'product_cross_selling',
                    label: this.$tc('sw-import-export.profile.productCrossSellingLabel'),
                },
            ],
            supportedDelimiter: [
                {
                    value: '^',
                    label: this.$tc('sw-import-export.profile.caretsLabel'),
                },
                {
                    value: ',',
                    label: this.$tc('sw-import-export.profile.commasLabel'),
                },
                {
                    value: ';',
                    label: this.$tc('sw-import-export.profile.semicolonLabel'),
                },
            ],
            supportedEnclosures: [
                {
                    value: '"',
                    label: this.$tc('sw-import-export.profile.doubleQuoteLabel'),
                },
            ],
            missingRequiredFields: [],
        };
    },

    computed: {
        ...mapPropertyErrors('profile',
            [
                'name',
                'sourceEntity',
                'delimiter',
                'enclosure',
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
        },

        profileRepository() {
            return this.repositoryFactory.create('import_export_profile');
        },
    },

    methods: {
        saveProfile() {
            this.getParentProfileSelected().then((parentProfile) => {
                this.checkValidation(parentProfile);

                if (this.missingRequiredFields.length === 0) {
                    this.$emit('profile-save');
                }
            });
        },

        getParentProfileSelected() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('sourceEntity', this.profile.sourceEntity));

            return this.profileRepository.search(criteria).then((results) => {
                if (results.total > 0) {
                    return results[0];
                }

                return null;
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-import-export.profile.messageSearchParentProfileError'),
                });
            });
        },

        checkValidation(parentProfile) {
            const parentMapping = parentProfile ? parentProfile.mapping : [];

            const validationErrors = Shopware.Service('importExportProfileMapping').validate(
                this.profile.sourceEntity,
                this.profile.mapping,
                parentMapping,
            );

            if (validationErrors.missingRequiredFields.length > 0) {
                this.missingRequiredFields = validationErrors.missingRequiredFields;
            }
        },

        resetViolations() {
            this.missingRequiredFields = [];
        },
    },
});
