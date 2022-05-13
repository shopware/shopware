import template from './sw-import-export-edit-profile-modal.html.twig';
import './sw-import-export-edit-profile-modal.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

const profileTypes = {
    IMPORT: 'import',
    EXPORT: 'export',
    IMPORT_EXPORT: 'import-export',
};

/**
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
        'importExportProfileMapping',
        'importExportUpdateByMapping',
    ],

    mixins: [Mixin.getByName('notification')],

    props: {
        profile: {
            type: Object,
            required: false,
            default() {
                return {};
            },
        },
        show: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default() {
                return true;
            },
        },
    },

    data() {
        return {
            /**
             * Array containing objects of the entities which are available for selection when editing a profile
             * object.value The name of the entity, also used as identifier in the select box
             * object.label The label of the entity
             * object.type Specifies the usage of the entity, possible values are 'import', 'export' or 'import-export'.
             *
             * @deprecated tag:v6.5.0 - will be moved into
             *   `sw-import-export-edit-profile-general`
             */
            supportedEntities: [
                {
                    value: 'product',
                    label: this.$tc('sw-import-export.profile.productLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'customer',
                    label: this.$tc('sw-import-export.profile.customerLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'category',
                    label: this.$tc('sw-import-export.profile.categoriesLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'order',
                    label: this.$tc('sw-import-export.profile.orderLabel'),
                    type: profileTypes.EXPORT,
                },
                {
                    value: 'media',
                    label: this.$tc('sw-import-export.profile.mediaLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'newsletter_recipient',
                    label: this.$tc('sw-import-export.profile.newsletterRecipientLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'property_group_option',
                    label: this.$tc('sw-import-export.profile.propertyLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'product_configurator_setting',
                    label: this.$tc('sw-import-export.profile.configuratorSettingLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'product_cross_selling',
                    label: this.$tc('sw-import-export.profile.productCrossSellingLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'promotion_discount',
                    label: this.$tc('sw-import-export.profile.promotionDiscountLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'promotion_individual_code',
                    label: this.$tc('sw-import-export.profile.promotionIndividualCodesLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
                {
                    value: 'product_price',
                    label: this.$tc('sw-import-export.profile.productPriceLabel'),
                    type: profileTypes.IMPORT_EXPORT,
                },
            ],

            /**
             * @deprecated tag:v6.5.0 - will be moved into
             *   `sw-import-export-edit-profile-field-indicators`
             */
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

            /**
             * @deprecated tag:v6.5.0 - will be moved into
             *   `sw-import-export-edit-profile-field-indicators`
             */
            supportedEnclosures: [
                {
                    value: '"',
                    label: this.$tc('sw-import-export.profile.doubleQuoteLabel'),
                },
            ],

            /**
             * @deprecated tag:v6.5.0 - will be moved into
             *   `sw-import-export-edit-profile-general`
             */
            supportedProfileTypes: [
                {
                    value: profileTypes.IMPORT_EXPORT,
                    label: this.$tc('sw-import-export.profile.types.importExportLabel'),
                },
                {
                    value: profileTypes.IMPORT,
                    label: this.$tc('sw-import-export.profile.types.importLabel'),
                },
                {
                    value: profileTypes.EXPORT,
                    label: this.$tc('sw-import-export.profile.types.exportLabel'),
                },
            ],
            missingRequiredFields: [],
            systemRequiredFields: {},
        };
    },

    computed: {
        ...mapPropertyErrors('profile',
            [
                'name',
                'sourceEntity',
                'delimiter',
                'enclosure',
                'type',
            ]),

        isNew() {
            if (!this.profile || !this.profile.isNew) {
                return false;
            }

            return this.profile.isNew();
        },

        /**
         * @deprecated tag:v6.5.0 - will be moved into
         *   `sw-import-export-edit-profile-general`
         */
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

    watch: {
        'profile.sourceEntity': {
            handler(value) {
                if (value) {
                    this.loadSystemRequiredFieldsForEntity(value);
                }
            },
        },
        'profile.mapping': {
            handler() {
                this.importExportUpdateByMapping.removeUnusedMappings(this.profile);
            },
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

        updateMapping(newProfile) {
            this.profile.mapping = newProfile;
        },

        getParentProfileSelected() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('sourceEntity', this.profile.sourceEntity));
            criteria.addFilter(Criteria.equals('systemDefault', true));

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
            // Skip validation for only export profiles
            if (this.profile.type === 'export') {
                return;
            }

            const parentMapping = parentProfile ? parentProfile.mapping : [];
            const isOnlyUpdateProfile =
                this.profile.config.createEntities === false &&
                this.profile.config.updateEntities === true;
            const validationErrors = this.importExportProfileMapping.validate(
                this.profile.sourceEntity,
                this.profile.mapping,
                parentMapping,
                isOnlyUpdateProfile,
            );

            if (validationErrors.missingRequiredFields.length > 0) {
                this.missingRequiredFields = validationErrors.missingRequiredFields;
            }
        },

        resetViolations() {
            this.missingRequiredFields = [];
        },

        loadSystemRequiredFieldsForEntity(entityName) {
            this.systemRequiredFields = this.importExportProfileMapping.getSystemRequiredFields(entityName);
        },

        /**
         * @deprecated tag:v6.5.0 - will be moved into
         *   `sw-import-export-edit-profile-import-settings` component
         */
        onCreateEntitiesChanged(newValue) {
            if (newValue === false) {
                this.profile.config.updateEntities = true;
            }
        },

        /**
         * @deprecated tag:v6.5.0 - will be moved into
         *   `sw-import-export-edit-profile-import-settings` component
         */
        onUpdateEntitiesChanged(newValue) {
            if (newValue === false) {
                this.profile.config.createEntities = true;
            }
        },

        /**
         * @deprecated tag:v6.5.0 - will be moved into
         *   `sw-import-export-edit-profile-general` component
         */
        shouldDisableProfileType(item) {
            if (!this.profile.sourceEntity) {
                return false;
            }
            const currentEntity = this.supportedEntities.find(entity => entity.value === this.profile.sourceEntity);
            if (currentEntity.type === profileTypes.IMPORT_EXPORT) {
                return false;
            }

            if (currentEntity.type === profileTypes.IMPORT) {
                return item.value !== profileTypes.IMPORT;
            }

            if (currentEntity.type === profileTypes.EXPORT) {
                return item.value !== profileTypes.EXPORT;
            }

            return true;
        },

        /**
         * @deprecated tag:v6.5.0 - will be moved into
         *   `sw-import-export-edit-profile-general` component
         */
        shouldDisableObjectType(item) {
            if (!this.profile.type) {
                return false;
            }

            if (this.profile.type === profileTypes.IMPORT_EXPORT) {
                return item.type !== profileTypes.IMPORT_EXPORT;
            }

            if (this.profile.type === profileTypes.IMPORT) {
                return ![profileTypes.IMPORT, profileTypes.IMPORT_EXPORT].includes(item.type);
            }

            if (this.profile.type === profileTypes.EXPORT) {
                return ![profileTypes.EXPORT, profileTypes.IMPORT_EXPORT].includes(item.type);
            }

            return true;
        },
    },
};
