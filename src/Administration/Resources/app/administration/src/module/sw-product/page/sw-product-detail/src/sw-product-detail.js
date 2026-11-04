/*
 * @sw-package inventory
 */

import template from './sw-product-detail.html.twig';
import errorConfiguration from './error.cfg.json';
import './sw-product-detail.scss';
import {
    aclService,
    customFieldDataProviderService as customFieldDataProviderServiceName,
    entityValidationService,
    featureService,
    getApiContext,
    getEntityDefinition,
    getUserConfigService,
    mapPageErrors,
    mediaService,
    notificationMixin,
    numberRangeService,
    placeholderMixin,
    publishData,
    repositoryFactoryService,
    seoUrlService,
    systemConfigApiService,
    useCmsPageStore,
    useContextStore,
    useProductDetailStore,
    useSessionStore,
    useShopwareAppsStore,
} from '../dependencies';
import { createGeneralTabSettings, createSpecificationsTabSettings, useAdvancedMode } from './advanced-mode/private';
import { collectSlotOverrides, stripSlotConfigMetadata } from './cms-page-overrides/private';
import { createProductDetailTabs } from './detail-tabs/private';
import { useProductCreation } from './product-creation/private';
import { createDefaultFeatureSetCriteria, createProductCriteria, useProductLoading } from './product-loading/private';
import { useProductSaving } from './product-saving/private';
import { getInheritedTitle, syncVariantPriceInheritance } from './variant-inheritance/private';

const concernsByPage = new WeakMap();

/**
 * The concerns of one page instance, created on first use.
 * Each concern calls the page's own methods for its steps, so an override of a step still applies.
 */
function concernsOf(page) {
    if (!concernsByPage.has(page)) {
        concernsByPage.set(page, {
            loading: useProductLoading(
                stepsOf(page, [
                    'loadProduct',
                    'loadParentProduct',
                    'loadCurrencies',
                    'loadTaxes',
                    'loadAttributeSet',
                    'loadDefaultFeatureSet',
                    'getDefaultPurchasePrices',
                    'onProductNotFound',
                ]),
                page,
            ),
            saving: useProductSaving(
                stepsOf(page, [
                    'saveProduct',
                    'onSaveFinished',
                    'customValidate',
                    'validateProductPurchase',
                    'validateProductPrices',
                    'validatePrices',
                ]),
                page,
            ),
            creation: useProductCreation(
                stepsOf(page, [
                    'adjustProductAccordingToType',
                    'getDefaultTaxRate',
                    'getDefaultSalesChannels',
                    'fetchSalesChannelByIds',
                    'createProductVisibilityEntity',
                ]),
                page,
            ),
            advancedMode: useAdvancedMode(
                stepsOf(page, [
                    'createUserModeSetting',
                    'getAdvancedModeDefaultSetting',
                    'getAdvancedModeSetting',
                    'saveAdvancedMode',
                    'changeModeSettings',
                ]),
                page,
            ),
        });
    }

    return concernsByPage.get(page);
}

/**
 * Functions that call the named methods of the page at call time, so they reach a method an override replaced.
 *
 * @example
 * stepsOf(page, ['loadProduct']).loadProduct(); // runs page.loadProduct(), overridden or not
 */
function stepsOf(page, names) {
    return Object.fromEntries(names.map((name) => [name, (...args) => page[name](...args)]));
}

/**
 * @public
 */
export default {
    template,

    inject: [
        featureService,
        mediaService,
        repositoryFactoryService,
        numberRangeService,
        seoUrlService,
        aclService,
        systemConfigApiService,
        customFieldDataProviderServiceName,
        entityValidationService,
    ],

    provide() {
        return {
            swProductDetailLoadAll: this.loadAll,
        };
    },

    mixins: [notificationMixin, placeholderMixin],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('product.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        productId: {
            type: String,
            required: false,
            default: null,
        },
        /**
         * @deprecated tag:v6.8.0 - will be removed, please use `creationType` instead
         */
        creationStates: {
            type: Array,
            required: false,
            default: null,
        },
        /* Product "type" provided by the split button for creating a new product through a router parameter */
        creationType: {
            type: String,
            required: false,
            default: 'physical',
        },
    },

    data() {
        return {
            productNumberPreview: '',
            isSaveSuccessful: false,
            cloning: false,
            defaultSalesChannelVisibility: 30,
            previousLengthUnit: null,
            previousWeightUnit: null,
            updateSeoPromises: [],
            isProductNotFound: false,
            lastViewedProductId: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        product() {
            return useProductDetailStore().product;
        },

        parentProduct() {
            return useProductDetailStore().parentProduct;
        },

        localMode() {
            return useProductDetailStore().localMode;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        advancedModeSetting() {
            return useProductDetailStore().advancedModeSetting;
        },

        modeSettings() {
            return useProductDetailStore().modeSettings;
        },

        isLoading() {
            return useProductDetailStore().isLoading;
        },

        isChild() {
            return useProductDetailStore().isChild;
        },

        defaultCurrency() {
            return useProductDetailStore().defaultCurrency;
        },

        getDefaultFeatureSet() {
            return useProductDetailStore().getDefaultFeatureSet;
        },

        showModeSetting() {
            return useProductDetailStore().showModeSetting;
        },

        advanceModeEnabled() {
            return useProductDetailStore().advanceModeEnabled;
        },

        productDetailTabs() {
            return createProductDetailTabs({
                productId: this.$route.params.id,
                isChild: this.isChild,
                showModeSetting: this.showModeSetting,
                generalError: this.swProductDetailBaseError,
                crossSellingError: this.swProductDetailCrossSellingError,
                translate: (...args) => this.$t(...args),
                navigate: (route) => {
                    void this.$router.push(route);
                },
            });
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, please use `productType` instead
         */
        productStates() {
            return useProductDetailStore().productStates;
        },

        productType() {
            return useProductDetailStore().productType;
        },

        isUploadedFileRequired() {
            return this.productType === 'digital' && !useProductDetailStore().isDownloadExternallyManaged;
        },

        ...mapPageErrors(errorConfiguration),

        identifier() {
            return this.productTitle;
        },

        productTitle() {
            // when product is variant
            if (this.isChild && this.product) {
                return this.getInheritTitle();
            }

            if (!this.$i18n) {
                return '';
            }

            // return name
            return this.placeholder(this.product, 'name', this.$t('sw-product.detail.textHeadline'));
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        syncRepository() {
            return this.repositoryFactory.create('product', null, {
                useSync: true,
            });
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        productVisibilityRepository() {
            return this.repositoryFactory.create('product_visibility');
        },

        mediaRepository() {
            if (this.product && this.product.media) {
                return this.repositoryFactory.create(this.product.media.entity, this.product.media.source);
            }
            return null;
        },

        featureSetRepository() {
            return this.repositoryFactory.create('product_feature_set');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        currentUser() {
            return useSessionStore().currentUser;
        },

        productCriteria() {
            return createProductCriteria();
        },

        defaultFeatureSetCriteria() {
            return createDefaultFeatureSetCriteria();
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getModeSettingGeneralTab() {
            return createGeneralTabSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getModeSettingSpecificationsTab() {
            return createSpecificationsTabSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        showAdvanceModeSetting() {
            if (this.isChild) {
                return false;
            }

            const routes = ['sw.product.detail.base', 'sw.product.detail.specifications'];

            return routes.includes(this.$route.name);
        },

        cmsPageState() {
            return useCmsPageStore();
        },

        currentPage() {
            return useCmsPageStore().currentPage;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        language() {
            return useContextStore().api.language;
        },

        translateFields() {
            if (!this.product) {
                return null;
            }

            return Object.keys(getEntityDefinition().getTranslatedFields(this.product.getEntityName()));
        },

        ignoreFieldsValidation() {
            if (!this.language?.parentId) {
                return [];
            }

            const productData = { ...this.product };

            // This filter identifies fields in a child language that are null, undefined, or empty.
            // These specific fields might be inheriting their values from the parent language,
            // so they are intentionally ignored during validation.
            return (this.translateFields || []).filter((field) => {
                const value = productData[field];

                return value === null || value === undefined || value === '';
            });
        },

        productApiContext() {
            return {
                ...getApiContext(),
                measurementWeightUnit: this.weightUnit,
                measurementLengthUnit: this.lengthUnit,
            };
        },

        lengthUnit() {
            return useProductDetailStore().lengthUnit;
        },

        weightUnit() {
            return useProductDetailStore().weightUnit;
        },

        measurementUnitsChanged() {
            return this.previousWeightUnit !== this.weightUnit || this.previousLengthUnit !== this.lengthUnit;
        },
    },

    watch: {
        productId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeRouteLeave() {
        useShopwareAppsStore().selectedIds = [];
    },

    methods: {
        async createdComponent() {
            this.isProductNotFound = false;

            publishData({
                id: 'sw-product-detail__product',
                path: 'product',
                scope: this,
            });

            publishData({
                id: 'sw-product-detail__cmsPage',
                path: 'currentPage',
                scope: this,
            });

            this.cmsPageState.resetCmsPageState();

            // when create
            if (!this.productId) {
                this.lastViewedProductId = null;
                // Immediately reset store to prevent
                // stale data from a previous variant/child product from rendering
                // before initState() creates a new product entity
                useProductDetailStore().$reset();

                // set language to system language
                if (!useContextStore().isSystemDefaultLanguage) {
                    useContextStore().resetLanguageToDefault();
                }
            }

            useProductDetailStore().setLoading(['product', true]);

            await this.initProductMeasurementUnits();

            // initialize default state
            this.initState();

            this.initAdvancedModeSettings();
        },

        initState() {
            useProductDetailStore().apiContext = getApiContext();

            // when product exists
            if (this.productId) {
                return this.loadState();
            }

            // When no product id exists init state and new product with the repositoryFactory
            return this.createState().then(() => {
                // create new product number
                this.numberRangeService.reserve('product', '', true).then((response) => {
                    this.productNumberPreview = response.number;
                    this.product.productNumber = response.number;
                });
            });
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        initAdvancedModeSettings() {
            return concernsOf(this).advancedMode.initAdvancedModeSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        createUserModeSetting() {
            return concernsOf(this).advancedMode.createUserModeSetting();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getAdvancedModeDefaultSetting() {
            return concernsOf(this).advancedMode.getAdvancedModeDefaultSetting();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getAdvancedModeSetting() {
            return concernsOf(this).advancedMode.getAdvancedModeSetting();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        saveAdvancedMode() {
            return concernsOf(this).advancedMode.saveAdvancedMode();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        onChangeSetting() {
            return concernsOf(this).advancedMode.onChangeSetting();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        changeModeSettings() {
            return concernsOf(this).advancedMode.changeModeSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        onChangeSettingItem() {
            return concernsOf(this).advancedMode.onChangeSettingItem();
        },

        loadState() {
            useProductDetailStore().localMode = false;
            useShopwareAppsStore().selectedIds = [this.productId];

            return this.loadAll();
        },

        loadAll() {
            return concernsOf(this).loading.loadAll();
        },

        createState() {
            return concernsOf(this).creation.createState();
        },

        adjustProductAccordingToType() {
            return concernsOf(this).creation.adjustProductAccordingToType();
        },

        loadProduct() {
            return concernsOf(this).loading.loadProduct();
        },

        onProductNotFound() {
            return concernsOf(this).loading.onProductNotFound();
        },

        syncVariantPriceInheritance() {
            syncVariantPriceInheritance(this.product, this.parentProduct);
        },

        getDefaultPurchasePrices() {
            return concernsOf(this).loading.getDefaultPurchasePrices();
        },

        loadParentProduct() {
            return concernsOf(this).loading.loadParentProduct();
        },

        loadCurrencies() {
            return concernsOf(this).loading.loadCurrencies();
        },

        loadTaxes() {
            return concernsOf(this).loading.loadTaxes();
        },

        getDefaultTaxRate() {
            return concernsOf(this).creation.getDefaultTaxRate();
        },

        loadAttributeSet() {
            return concernsOf(this).loading.loadAttributeSet();
        },

        loadDefaultFeatureSet() {
            return concernsOf(this).loading.loadDefaultFeatureSet();
        },

        getDefaultSalesChannels() {
            return concernsOf(this).creation.getDefaultSalesChannels();
        },

        fetchSalesChannelByIds(ids) {
            return concernsOf(this).creation.fetchSalesChannelByIds(ids);
        },

        createProductVisibilityEntity(visibility, salesChannel) {
            return concernsOf(this).creation.createProductVisibilityEntity(visibility, salesChannel);
        },

        abortOnLanguageChange() {
            return this.productRepository.hasChanges(this.product);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            useContextStore().setApiLanguageId(languageId);
            this.loadLanguage(languageId);

            this.initState();
        },

        saveFinish() {
            return concernsOf(this).saving.saveFinish();
        },

        onSave() {
            return concernsOf(this).saving.onSave();
        },

        customValidate(errors, product) {
            return concernsOf(this).saving.customValidate(errors, product);
        },

        validateProductPrices() {
            return concernsOf(this).saving.validateProductPrices();
        },

        validatePrices(priceLabel, prices) {
            return concernsOf(this).saving.validatePrices(priceLabel, prices);
        },

        onSaveFinished(response) {
            return concernsOf(this).saving.onSaveFinished(response);
        },

        onCancel() {
            this.$router.push({ name: 'sw.product.index' });
        },

        saveProduct() {
            return concernsOf(this).saving.saveProduct();
        },

        removeMediaItem(state, mediaId) {
            const media = this.product.media.find((mediaItem) => mediaItem.mediaId === mediaId);

            // remove cover id if mediaId matches
            if (this.product.coverId === media.id) {
                this.product.coverId = null;
            }

            this.product.media.remove(mediaId);
        },

        onCoverChange(mediaId) {
            if (!mediaId || mediaId.length < 0) {
                return;
            }

            const media = this.product.media.find((mediaItem) => mediaItem.mediaId === mediaId);

            if (media) {
                this.product.coverId = media.id;
            }
        },

        getInheritTitle() {
            return getInheritedTitle(this.product, this.parentProduct);
        },

        onDuplicate() {
            this.cloning = true;
        },

        onDuplicateFinish(duplicate) {
            this.cloning = false;
            this.$router.push({
                name: 'sw.product.detail',
                params: { id: duplicate.id },
            });
        },

        validateProductPurchase() {
            return concernsOf(this).saving.validateProductPurchase();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getCmsPageOverrides() {
            if (this.currentPage === null) {
                return null;
            }

            this.deleteSpecifcKeys(this.currentPage.sections);

            return collectSlotOverrides(this.currentPage);
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        deleteSpecifcKeys(sections) {
            stripSlotConfigMetadata(sections);
        },

        async loadLanguage(newLanguageId) {
            useContextStore().api.language = await this.languageRepository.get(newLanguageId, {
                ...getApiContext(),
                inheritance: true,
            });
        },

        async initProductMeasurementUnits() {
            const preferenceUnits = await this.getPreferredMeasurementUnits();
            const store = useProductDetailStore();

            const defaultUnits = {
                length: store.lengthUnit,
                weight: store.weightUnit,
            };

            const units = preferenceUnits || defaultUnits;

            store.setLengthUnit(units.length);
            store.setWeightUnit(units.weight);

            this.previousLengthUnit = units.length;
            this.previousWeightUnit = units.weight;
        },

        async getPreferredMeasurementUnits() {
            try {
                return (await getUserConfigService().search(['measurement.preferenceUnits']))?.data?.[
                    'measurement.preferenceUnits'
                ];
            } catch {
                // the product must not stay in its loading state when the preferences cannot be read,
                // initProductMeasurementUnits() falls back to the default units instead
                return null;
            }
        },

        savePreferenceUnits() {
            if (!this.measurementUnitsChanged) {
                return Promise.resolve();
            }

            return getUserConfigService().upsert({
                'measurement.preferenceUnits': {
                    length: this.lengthUnit,
                    weight: this.weightUnit,
                },
            });
        },
    },
};
