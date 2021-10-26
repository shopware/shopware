import template from './sw-bulk-edit-product.html.twig';
import './sw-bulk-edit-product.scss';
import swProductDetailState from '../../../sw-product/page/sw-product-detail/state';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { chunk } = Shopware.Utils.array;
const { mapState, mapGetters } = Component.getComponentHelper();

Component.register('sw-bulk-edit-product', {
    template,

    inject: [
        'feature',
        'bulkEditApiFactory',
        'repositoryFactory',
    ],

    data() {
        return {
            isLoading: false,
            isLoadedData: false,
            isSaveSuccessful: false,
            displayAdvancePricesModal: false,
            isDisabledListPrice: true,
            bulkEditProduct: {},
            bulkEditSelected: [],
            taxRate: {},
            currency: {},
            customFieldSets: [],
            processStatus: '',
            rules: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'taxes',
        ]),

        ...mapGetters('swProductDetail', [
            'defaultCurrency',
            'defaultPrice',
        ]),

        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        hasSelectedChanges() {
            return Object.values(this.bulkEditProduct).some(field => field.isChanged) || this.bulkEditSelected.length > 0;
        },
        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'product'));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        currencyCriteria() {
            return (new Criteria())
                .addSorting(Criteria.sort('name', 'ASC'));
        },

        taxCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('position'));

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

        pricesFormFields() {
            this.definePricesBulkEdit();

            return [{
                name: 'taxId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'tax',
                    changeLabel: this.$tc('sw-bulk-edit.product.prices.taxRate.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.prices.taxRate.placeholderTax'),
                },
            }, {
                name: 'price',
                config: {
                    componentName: 'sw-price-field',
                    price: this.product.price,
                    taxRate: this.taxRate,
                    currency: this.currency,
                    changeLabel: this.$tc('sw-bulk-edit.product.prices.price.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.prices.price.placeholderPrice'),
                },
            }, {
                name: 'listPrice',
                config: {
                    componentName: 'sw-price-field',
                    price: this.product.listPrice,
                    disabled: this.isDisabledListPrice,
                    taxRate: this.taxRate,
                    currency: this.currency,
                    changeLabel: this.$tc('sw-bulk-edit.product.prices.listPrice.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.prices.listPrice.placeholderListPrice'),
                },
            }, {
                name: 'purchasePrices',
                config: {
                    componentName: 'sw-price-field',
                    price: this.product.purchasePrices,
                    taxRate: this.taxRate,
                    currency: this.currency,
                    allowOverwrite: true,
                    allowClear: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.prices.purchasePrices.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.prices.purchasePrices.placeholderPurchasePrices'),
                },
            }];
        },

        advancedPricesFormFields() {
            return [{
                name: 'prices',
                config: {
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: true,
                    allowRemove: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.advancedPrices.changeLabel'),
                },
            }];
        },

        propertyFormFields() {
            return [{
                name: 'properties',
                config: {
                    componentName: 'sw-product-properties',
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: true,
                    allowRemove: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.property.changeLabel'),
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
                    placeholder: this.$tc('sw-bulk-edit.product.deliverability.stock.placeholderStock'),
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
                    placeholder: this.$tc('sw-bulk-edit.product.deliverability.restockTime.placeholderRestockTime'),
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
                    placeholder:
                        this.$tc('sw-bulk-edit.product.deliverability.minOrderQuantity.placeholderMinOrderQuantity'),
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
                    placeholder: this.$tc('sw-bulk-edit.product.deliverability.purchaseSteps.placeholderPurchaseSteps'),
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
                    placeholder:
                        this.$tc('sw-bulk-edit.product.deliverability.maxOrderQuantity.placeholderMaxOrderQuantity'),
                    numberType: 'int',
                    allowOverwrite: true,
                    allowClear: true,
                    allowEmpty: true,
                    min: 0,
                },
            }];
        },

        assignmentFormFields() {
            return [{
                name: 'visibilities',
                config: {
                    componentName: 'sw-bulk-edit-product-visibility',
                    bulkEditProduct: this.bulkEditProduct,
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: true,
                    allowRemove: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.assignment.visibilities.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.assignment.visibilities.placeholder'),
                },
            }, {
                name: 'categories',
                config: {
                    componentName: 'sw-category-tree-field',
                    categoriesCollection: this.product.categories,
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: true,
                    allowRemove: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.assignment.categories.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.assignment.categories.placeholder'),
                },
            }, {
                name: 'tags',
                config: {
                    componentName: 'sw-entity-tag-select',
                    entityCollection: this.product.tags,
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: true,
                    allowRemove: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.assignment.tags.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.assignment.tags.placeholder'),
                },
            }, {
                name: 'searchKeywords',
                config: {
                    componentName: 'sw-multi-tag-select',
                    value: this.product.searchKeywords,
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: false,
                    allowRemove: false,
                    changeLabel: this.$tc('sw-bulk-edit.product.assignment.searchKeywords.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.assignment.searchKeywords.placeholder'),
                },
            }];
        },

        mediaFormFields() {
            return [{
                name: 'media',
                config: {
                    componentName: 'sw-bulk-edit-product-media',
                    allowOverwrite: true,
                    allowClear: true,
                    allowAdd: true,
                    changeLabel: this.$tc('sw-bulk-edit.product.media.changeLabel'),
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
                    placeholder: this.$tc('sw-bulk-edit.product.seo.metaTitle.placeholderMetaTitle'),
                },
            }, {
                name: 'metaDescription',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'textarea',
                    changeLabel: this.$tc('sw-bulk-edit.product.seo.metaDescription.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.seo.metaDescription.placeholderMetaDescription'),
                },
            }, {
                name: 'keywords',
                type: 'text',
                config: {
                    componentName: 'sw-field',
                    type: 'text',
                    changeLabel: this.$tc('sw-bulk-edit.product.seo.seoKeywords.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.seo.seoKeywords.placeholderSeoKeywords'),
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

        essentialCharacteristicsFormFields() {
            return [{
                name: 'featureSetId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'product_feature_set',
                    changeLabel: this.$tc('sw-bulk-edit.product.featureSets.changeLabel'),
                    placeholder: this.$tc('sw-bulk-edit.product.featureSets.placeholder'),
                },
            }];
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        priceRepository() {
            if (!this.product?.prices) {
                return null;
            }

            return this.repositoryFactory.create(
                this.product.prices.entity,
                this.product.prices.source,
            );
        },

        ruleCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null),
                ]),
            );

            return criteria;
        },

        priceRuleGroups() {
            if (!this.product.prices) {
                return {};
            }

            return this.product.prices.reduce((r, a) => {
                r[a.ruleId] = [...r[a.ruleId] || [], a];
                return r;
            }, {});
        },
    },

    watch: {
        'bulkEditProduct.prices': {
            handler(value) {
                if (
                    !this.feature.isActive('FEATURE_NEXT_17261') ||
                    !this.product?.prices?.length ||
                    value?.type !== 'remove'
                ) {
                    return;
                }

                const ids = this.product.prices?.getIds();
                ids.forEach(id => this.product.prices.remove(id));
            },
            deep: true,
        },
    },

    beforeCreate() {
        Shopware.State.registerModule('swProductDetail', swProductDetailState);
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swProductDetail');
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            const promises = [
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadCustomFieldSets(),
                this.loadDefaultCurrency(),
                this.loadRules(),
            ];

            Promise.all(promises).then(() => {
                this.loadBulkEditData();

                Shopware.State.commit('swProductDetail/setProduct', this.productRepository.create());

                this.isLoading = false;
                this.isLoadedData = true;
            });
        },

        loadDefaultCurrency() {
            return this.currencyRepository.search(this.currencyCriteria).then((currencies) => {
                this.currency = currencies.find(currency => currency.isSystemDefault);
            });
        },

        defineBulkEditData(name, value = null, type = 'overwrite', isChanged = false) {
            if (this.bulkEditProduct[name]) {
                return;
            }

            this.$set(this.bulkEditProduct, name, {
                isChanged: isChanged,
                type: type,
                value: value,
            });
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [
                this.generalFormFields,
                this.deliverabilityFormFields,
                this.pricesFormFields,
                this.advancedPricesFormFields,
                this.propertyFormFields,
                this.assignmentFormFields,
                this.mediaFormFields,
                this.labellingFormFields,
                this.seoFormFields,
                this.measuresPackagingFields,
                this.essentialCharacteristicsFormFields,
            ];

            bulkEditFormGroups.forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.defineBulkEditData(bulkEditForm.name);
                });
            });
        },

        loadCustomFieldSets() {
            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        loadTaxes() {
            return this.taxRepository.search(this.taxCriteria).then((taxes) => {
                this.taxRate = taxes[0];
                Shopware.State.commit('swProductDetail/setTaxes', taxes);
            });
        },

        productTaxRate() {
            if (!this.taxes) {
                return {};
            }

            return this.taxes.find((tax) => {
                return tax.id === this.product.taxId;
            });
        },

        loadCurrencies() {
            return this.currencyRepository.search(new Criteria(1, 500)).then((res) => {
                Shopware.State.commit('swProductDetail/setCurrencies', res);
            });
        },

        definePricesBulkEdit() {
            this.product.price = [{
                currencyId: this.currency.id,
                net: null,
                linked: true,
                gross: null,
            }];

            this.product.purchasePrices = [{
                currencyId: this.currency.id,
                net: null,
                linked: true,
                gross: null,
            }];

            this.product.listPrice = [{
                currencyId: this.currency.id,
                net: null,
                linked: true,
                gross: null,
            }];
        },

        onChangePrices(item) {
            if (item === 'taxId') {
                this.taxRate = this.productTaxRate();
            } else if (item === 'price') {
                this.isDisabledListPrice = !this.bulkEditProduct.price.isChanged;
            }
        },

        onCustomFieldsChange(value) {
            if (Object.keys(value).length <= 0) {
                this.bulkEditSelected = this.bulkEditSelected.filter(change => change.field !== 'customFields');
                return;
            }

            const change = {
                field: 'customFields',
                type: 'overwrite',
                value: value,
            };

            this.bulkEditSelected.push(change);
        },

        onProcessData() {
            let hasListPrice = false;

            Object.keys(this.bulkEditProduct).forEach(key => {
                const bulkEditField = this.bulkEditProduct[key];
                if (!bulkEditField.isChanged) {
                    return;
                }

                if (key === 'listPrice') {
                    hasListPrice = true;

                    return;
                }

                let bulkEditValue = this.product[key];

                if (['minPurchase', 'maxPurchase', 'purchaseSteps', 'restockTime'].includes(key) &&
                    bulkEditField.type === 'clear') {
                    bulkEditValue = null;
                    bulkEditField.type = 'overwrite';
                } else if (key === 'searchKeywords') {
                    key = 'customSearchKeywords';
                }

                const change = {
                    field: key,
                    type: bulkEditField.type,
                    value: bulkEditValue,
                };

                if (key === 'visibilities') {
                    change.mappingReferenceField = 'salesChannelId';
                } else if (key === 'media') {
                    change.mappingReferenceField = 'mediaId';
                } else if (key === 'prices') {
                    change.mappingReferenceField = 'ruleId';
                }

                this.bulkEditSelected.push(change);
            });

            if (hasListPrice) {
                this.processListPrice();
            }
        },

        processListPrice() {
            const priceField = this.bulkEditSelected.find((dataField) => {
                return dataField.field === 'price';
            });

            if (priceField) {
                this.$set(priceField.value[0], 'listPrice', this.product.listPrice[0]);
            }
        },

        openModal() {
            this.$router.push({ name: 'sw.bulk.edit.product.save' });
        },

        async onSave() {
            this.isLoading = true;
            this.onProcessData();

            const payloadChunks = chunk(this.selectedIds, 50);

            const requests = payloadChunks.map(payload => {
                return this.bulkEditApiFactory.getHandler('product')
                    .bulkEdit(payload, this.bulkEditSelected);
            });

            this.bulkEditSelected = [];

            return Promise.all(requests)
                .then(response => {
                    const isSuccessful = response.every(item => item.data);
                    this.processStatus = isSuccessful ? 'success' : 'fail';
                }).catch(() => {
                    this.processStatus = 'fail';
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        closeModal() {
            this.$router.push({ name: 'sw.bulk.edit.product' });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
        },

        loadRules() {
            if (!this.feature.isActive('FEATURE_NEXT_17261')) {
                return Promise.resolve();
            }

            return this.ruleRepository.search(this.ruleCriteria).then((res) => {
                this.rules = res;
            });
        },

        onRuleChange(rules) {
            if (rules.length > this.product.prices.length) {
                const newPriceRule = this.priceRepository.create();

                newPriceRule.productId = this.product.id;
                newPriceRule.quantityStart = 1;
                newPriceRule.quantityEnd = null;
                newPriceRule.currencyId = this.defaultCurrency.id;
                newPriceRule.price = [{
                    currencyId: this.defaultCurrency.id,
                    gross: 0,
                    linked: this.defaultPrice.linked,
                    net: 0,
                    listPrice: null,
                }];

                if (this.defaultPrice.listPrice) {
                    newPriceRule.price[0].listPrice = {
                        currencyId: this.defaultCurrency.id,
                        gross: this.defaultPrice.listPrice.gross,
                        linked: this.defaultPrice.listPrice.linked,
                        net: this.defaultPrice.listPrice.net,
                    };
                }

                rules.forEach(rule => {
                    if (this.product.prices.some(item => item.ruleId === rule.ruleId)) {
                        return;
                    }

                    newPriceRule.ruleId = rule.id;
                    newPriceRule.ruleName = rule.name;

                    this.product.prices.add(newPriceRule);
                });

                return;
            }

            this.product.prices.forEach(price => {
                if (rules.some(rule => price.ruleId === rule.ruleId)) {
                    return;
                }

                this.product.prices.remove(price.id);
            });
        },
    },
});

