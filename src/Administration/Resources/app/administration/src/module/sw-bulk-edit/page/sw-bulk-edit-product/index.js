import template from './sw-bulk-edit-product.html.twig';
import './sw-bulk-edit-product.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-bulk-edit-product', {
    template,

    inject: [
        'bulkEditApiFactory',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            bulkEditProduct: {},
            customFieldSets: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        bulkEditService() {
            return this.bulkEditApiFactory.getHandler('product');
        },

        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'product'));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        generalFormFields() {
            return [{
                name: 'description',
                type: 'html',
                config: {
                    componentName: 'sw-text-editor',
                    changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.description.changeLabel'),
                },
            }, {
                name: 'manufacturerId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'product_manufacturer',
                    changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.manufacturer.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.generalInformation.manufacturer.placeholderManufacturer'),
                },
            }, {
                name: 'active',
                type: 'bool',
                config: {
                    type: 'switch',
                    label: this.$tc('sw-bulk-edit.product.generalInformation.active.switchLabel'),
                    changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.active.changeLabel'),
                },
            }, {
                name: 'markAsTopseller',
                type: 'bool',
                config: {
                    type: 'switch',
                    label: this.$tc('sw-bulk-edit.product.generalInformation.productPromotion.switchLabel'),
                    changeLabel: this.$tc('sw-bulk-edit.product.generalInformation.productPromotion.changeLabel'),
                },
            }];
        },

        deliverabilityFormFields() {
            return [{
                name: 'stock',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.stock.changeLabel'),
                    numberType: 'int',
                    allowEmpty: true,
                    allowOverwrite: true,
                    allowClear: true,
                    min: 0,
                },
            }, {
                name: 'isCloseout',
                type: 'bool',
                config: {
                    type: 'switch',
                    label: this.$tc('sw-bulk-edit.product.deliverability.isCloseout.switchLabel'),
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.isCloseout.changeLabel'),
                },
            }, {
                name: 'deliveryTimeId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'delivery_time',
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.deliveryTime.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.deliverability.deliveryTime.placeholderDeliveryTime'),
                },
            }, {
                name: 'restockTime',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.restockTime.changeLabel'),
                    numberType: 'int',
                    allowEmpty: true,
                    allowOverwrite: true,
                    allowClear: true,
                    min: 0,
                },
            }, {
                name: 'shippingFree',
                type: 'bool',
                config: {
                    type: 'switch',
                    label: this.$tc('sw-bulk-edit.product.deliverability.freeShipping.switchLabel'),
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.freeShipping.changeLabel'),
                },
            }, {
                name: 'minPurchase',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.minOrderQuantity.changeLabel'),
                    numberType: 'int',
                    allowOverwrite: true,
                    allowClear: true,
                    allowEmpty: true,
                    min: 1,
                },
            }, {
                name: 'purchaseSteps',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.purchaseSteps.changeLabel'),
                    numberType: 'int',
                    allowOverwrite: true,
                    allowClear: true,
                    allowEmpty: true,
                    min: 1,
                },
            }, {
                name: 'maxPurchase',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.deliverability.maxOrderQuantity.changeLabel'),
                    numberType: 'int',
                    allowOverwrite: true,
                    allowClear: true,
                    allowEmpty: true,
                    min: 0,
                },
            }];
        },

        labellingFormFields() {
            return [{
                name: 'releaseDate',
                type: 'datetime',
                config: {
                    type: 'date',
                    dateType: 'datetime-local',
                    changeLabel: this.$tc('sw-bulk-edit.product.labelling.releaseDate.changeLabel'),
                },
            }];
        },

        seoFormFields() {
            return [{
                name: 'metaTitle',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'text',
                    changeLabel: this.$tc('sw-bulk-edit.product.seo.metaTitle.changeLabel'),
                },
            }, {
                name: 'metaDescription',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'textarea',
                    changeLabel: this.$tc('sw-bulk-edit.product.seo.metaDescription.changeLabel'),
                },
            }, {
                name: 'keywords',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'text',
                    changeLabel: this.$tc('sw-bulk-edit.product.seo.seoKeywords.changeLabel'),
                },
            }];
        },

        measuresPackagingFields() {
            return [{
                name: 'width',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.widthTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.widthTitle.placeholder'),
                    numberType: 'float',
                    suffixLabel: 'mm',
                    min: 0,
                },
            }, {
                name: 'height',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.heightTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.heightTitle.placeholder'),
                    numberType: 'float',
                    suffixLabel: 'mm',
                    min: 0,
                },
            }, {
                name: 'length',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.lengthTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.lengthTitle.placeholder'),
                    numberType: 'float',
                    suffixLabel: 'mm',
                    min: 0,
                },
            }, {
                name: 'weight',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.weightTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.weightTitle.placeholder'),
                    numberType: 'float',
                    suffixLabel: 'kg',
                    min: 0,
                },
            }, {
                name: 'purchaseUnit',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    numberType: 'float',
                    min: 0,
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.sellingUnitTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.sellingUnitTitle.placeholder'),
                },
            }, {
                name: 'unitId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'unit',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.scaleUnitTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.scaleUnitTitle.placeholder'),
                },
            }, {
                name: 'packUnit',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'text',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitTitle.placeholder'),
                },
            }, {
                name: 'packUnitPlural',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'text',
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitPluralTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.packUnitPluralTitle.placeholder'),
                },
            }, {
                name: 'referenceUnit',
                type: 'int',
                config: {
                    componentName: 'sw-number-field',
                    numberType: 'float',
                    min: 0,
                    changeLabel: this.$tc('sw-bulk-edit.product.measuresAndPackaging.basicUnitTitle.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.measuresAndPackaging.basicUnitTitle.placeholder'),
                },
            }];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            this.loadBulkEditData();
            this.loadCustomFieldSets();
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [
                this.generalFormFields,
                this.deliverabilityFormFields,
                this.labellingFormFields,
                this.seoFormFields,
                this.measuresPackagingFields,
            ];

            bulkEditFormGroups.forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.$set(this.bulkEditProduct, bulkEditForm.name, {
                        isChanged: false,
                        type: 'overwrite',
                        value: null,
                    });
                });
            });

            this.bulkEditProduct.customFields = {
                isChanged: false,
                type: 'overwrite',
                value: {},
            };
        },

        loadCustomFieldSets() {
            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        onCustomFieldsChange(status) {
            this.bulkEditProduct.customFields.isChanged = status;
        },

        onProcessData() {
            const data = [];
            Object.keys(this.bulkEditProduct).forEach(key => {
                const item = this.bulkEditProduct[key];
                if (item.isChanged) {
                    data.push({
                        field: key,
                        type: item.type,
                        value: item.value,
                    });
                }
            });

            return data;
        },

        async onSave() {
            this.isLoading = true;

            const data = this.onProcessData();
            await this.bulkEditService.bulkEdit(this.selectedIds, data).then(() => {
                this.isLoading = false;

                // TODO NEXT-15507 - Product bulk edit processing
                this.createNotificationSuccess({
                    message: 'Edit successful',
                });
            }).catch(() => {
                this.isLoading = false;

                // TODO NEXT-15507 - Product bulk edit processing
                this.createNotificationError({
                    message: 'Error',
                });
            });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
        },
    },
});

