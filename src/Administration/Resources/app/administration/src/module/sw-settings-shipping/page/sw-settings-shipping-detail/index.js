import { mapPropertyErrors } from 'src/app/service/map-errors.service';
import template from './sw-settings-shipping-detail.html.twig';
import './store';

const { Mixin, Context, Application } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'ruleConditionDataProviderService',
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        shippingMethodId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            logoMediaItem: null,
            uploadTag: 'sw-shipping-method-upload-tag',
            isSaveSuccessful: false,
            isProcessLoading: false,
            isLoading: false,
            currenciesLoading: false,
            customFieldSets: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        shippingMethod() {
            return Shopware.Store.get('swShippingDetail').shippingMethod;
        },

        currencies() {
            return Shopware.Store.get('swShippingDetail').currencies;
        },

        restrictedRuleIds() {
            return Shopware.Store.get('swShippingDetail').restrictedRuleIds;
        },

        calculatePriceApiService() {
            return Application.getContainer('factory').apiService.getByName('calculate-price');
        },

        // Only a "fixed" tax type has a known rate; "auto" and "highest" depend on the cart.
        fixedTaxId() {
            return this.shippingMethod.taxType === 'fixed' ? this.shippingMethod.taxId : null;
        },

        ...mapPropertyErrors('shippingMethod', [
            'name',
            'technicalName',
            'deliveryTimeId',
            'availabilityRuleId',
        ]),

        identifier() {
            return this.placeholder(this.shippingMethod, 'name');
        },

        shippingMethodRepository() {
            return this.repositoryFactory.create('shipping_method');
        },

        shippingMethodPricesRepository() {
            return this.repositoryFactory.create('shipping_method_price');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        isNewShippingMethod() {
            return Object.keys(this.shippingMethod).length > 0 && this.shippingMethod.isNew();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        deliveryTimeRepository() {
            return this.repositoryFactory.create('delivery_time');
        },

        deliveryTimeCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('unit'));
            criteria.addSorting(Criteria.sort('name'));
            return criteria;
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

        ruleFilter() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'shipping'),
                    Criteria.equals('rule.moduleTypes', null),
                ]),
            );

            criteria.addAssociation('conditions');

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('tags');

            criteria.getAssociation('prices').addAssociation('rule').addSorting(Criteria.sort('quantityStart'));

            return criteria;
        },

        showCustomFields() {
            return this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        'shippingMethod.mediaId'() {
            if (this.shippingMethod.mediaId) {
                this.setMediaItem({ targetId: this.shippingMethod.mediaId });
            }
        },

        shippingMethodId() {
            // We must reset the page if the user clicks his browsers back button and navigates back to create
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.shippingMethodId) {
                Shopware.Store.get('context').resetLanguageToDefault();

                const shippingMethod = this.shippingMethodRepository.create();
                const shippingMethodPrice = this.shippingMethodPricesRepository.create();
                shippingMethodPrice.calculation = 1;
                shippingMethodPrice.quantityStart = 0;
                shippingMethodPrice.shippingMethodId = shippingMethod.id;
                shippingMethodPrice.ruleId = null;
                shippingMethod.prices.add(shippingMethodPrice);
                Shopware.Store.get('swShippingDetail').shippingMethod = shippingMethod;
            } else {
                this.loadEntityData();
            }
            this.loadCurrencies();
        },

        onSaveRule(ruleId) {
            this.shippingMethod.availabilityRuleId = ruleId;
        },

        loadCurrencies() {
            this.currenciesLoading = true;
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('salesChannels');
            this.currencyRepository.search(criteria, Context.api).then((currencyResponse) => {
                Shopware.Store.get('swShippingDetail').currencies = this.sortCurrencies(currencyResponse);
                this.currenciesLoading = false;
            });
        },

        loadEntityData() {
            if (!this.shippingMethodId) {
                return;
            }

            this.isLoading = true;

            this.shippingMethodRepository
                .get(this.shippingMethodId, Shopware.Context.api, this.shippingMethodCriteria)
                .then((res) => {
                    Shopware.Store.get('swShippingDetail').shippingMethod = res;

                    this.ruleConditionDataProviderService.getRestrictedRules('shippingMethodPrices').then((result) => {
                        Shopware.Store.get('swShippingDetail').restrictedRuleIds = this.restrictedRuleIds.concat(result);
                    });

                    this.loadCustomFieldSets().then(() => {
                        this.isLoading = false;
                    });
                });
        },

        loadCustomFieldSets() {
            return this.customFieldDataProviderService.getCustomFieldSets('shipping_method').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        abortOnLanguageChange() {
            return this.shippingMethodRepository.hasChanges(this.shippingMethod);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        async onSave() {
            this.filterIncompletePrices();

            this.isSaveSuccessful = false;
            this.isProcessLoading = true;

            await this.recalculateLinkedPrices();

            return this.shippingMethodRepository
                .save(this.shippingMethod, Context.api)
                .then(() => {
                    this.isSaveSuccessful = true;
                    if (!this.shippingMethodId) {
                        this.$router.push({
                            name: 'sw.settings.shipping.detail',
                            params: { id: this.shippingMethod.id },
                        });
                    }
                    this.$refs.mediaSidebarItem.getList();
                    this.loadEntityData();
                })
                .catch((exception) => {
                    this.onError(exception);
                    warn(this._name, exception.message, exception.response);
                    this.isProcessLoading = false;
                    throw exception;
                })
                .finally(() => {
                    this.isProcessLoading = false;
                });
        },

        onError(error) {
            let errorDetails = null;

            try {
                errorDetails = error.response.data.errors[0].detail;
            } catch (_e) {
                errorDetails = '';
            }

            this.createNotificationError({
                title: this.$t('global.default.error'),
                message: `${this.$t('sw-settings-shipping.detail.messageSaveError', { name: this.shippingMethod.name }, 0)} ${errorDetails}`,
            });
        },

        /**
         * `sw-price-field` calculates a linked price debounced and asynchronously, so saving right
         * after an edit would persist a stale counterpart. Recalculate every linked price from its
         * gross value first — this covers price tiers and currency columns that are not rendered.
         */
        async recalculateLinkedPrices() {
            if (!this.fixedTaxId) {
                return;
            }

            const prices = {};

            this.shippingMethod.prices?.forEach((shippingPrice) => {
                const linked = (shippingPrice.currencyPrice ?? []).filter((price) => price.linked && price.gross);

                if (linked.length) {
                    prices[shippingPrice.id] = linked.map((price) => ({
                        currencyId: price.currencyId,
                        price: price.gross,
                        output: 'gross',
                    }));
                }
            });

            if (!Object.keys(prices).length) {
                return;
            }

            try {
                // `calculatePrices` already unwraps the response body, unlike `calculatePrice`.
                const calculated = await this.calculatePriceApiService.calculatePrices(this.fixedTaxId, prices);

                this.shippingMethod.prices.forEach((shippingPrice) => {
                    shippingPrice.currencyPrice?.forEach((price) => {
                        const result = calculated?.[shippingPrice.id]?.[price.currencyId];

                        if (!price.linked || !result) {
                            return;
                        }

                        const tax = result.calculatedTaxes.reduce((sum, item) => sum + item.tax, 0);
                        price.net = parseFloat((price.gross - tax).toPrecision(14));
                    });
                });
            } catch (exception) {
                // Keep the entered prices saveable when the tax calculation is unavailable.
                warn(this._name, exception.message);
            }
        },

        filterIncompletePrices() {
            this.getIncompletePrices().forEach((incompletePrice) => {
                this.shippingMethod.prices.remove(incompletePrice.id);
            });
        },

        getIncompletePrices() {
            return this.shippingMethod.prices.filter((price) => {
                return (!price.calculation && !price.calculationRuleId) || price._inNewMatrix;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.shipping.index' });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId, Context.api).then((updatedMedia) => {
                this.logoMediaItem = updatedMedia;
            });
            this.shippingMethod.mediaId = targetId;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        setMediaFromSidebar(mediaEntity) {
            this.logoMediaItem = mediaEntity;
            this.shippingMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.logoMediaItem = null;
            this.shippingMethod.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        sortCurrencies(currencies) {
            currencies.sort((a, b) => {
                if (a.isSystemDefault) {
                    return -1;
                }
                if (b.isSystemDefault) {
                    return 1;
                }
                if (a.translated.name < b.translated.name) {
                    return -1;
                }
                if (a.translated.name > b.translated.name) {
                    return 1;
                }
                return 0;
            });

            return currencies;
        },
    },
};
