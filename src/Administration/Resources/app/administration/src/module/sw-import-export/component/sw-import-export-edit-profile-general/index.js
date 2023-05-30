/**
 * @package system-settings
 */
import { mapPropertyErrors } from 'src/app/service/map-errors.service';
import template from './sw-import-export-edit-profile-general.html.twig';
import './sw-import-export-edit-profile-general.scss';

const profileTypes = {
    IMPORT: 'import',
    EXPORT: 'export',
    IMPORT_EXPORT: 'import-export',
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    props: {
        profile: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors(
            'profile',
            [
                'name',
                'sourceEntity',
                'type',
            ],
        ),

        supportedProfileTypes() {
            return [
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
            ];
        },

        supportedEntities() {
            return [
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
            ];
        },

        mappingLength() {
            return this.profile.mapping ? this.profile.mapping.length : 0;
        },
    },

    methods: {
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

