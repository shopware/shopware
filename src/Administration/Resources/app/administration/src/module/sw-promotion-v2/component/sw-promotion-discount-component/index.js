import { DiscountTypes, DiscountScopes, PromotionPermissions } from 'src/module/sw-promotion-v2/helper/promotion.helper';
import template from './sw-promotion-discount-component.html.twig';
import './sw-promotion-discount-component.scss';
import DiscountHandler from './handler';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const discountHandler = new DiscountHandler();

/**
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
        'ruleConditionDataProviderService',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        promotion: {
            type: Object,
            required: true,
        },
        discount: {
            type: Object,
            required: true,
        },
    },
    data() {
        return {
            displayAdvancedPrices: false,
            currencies: [],
            defaultCurrency: null,
            isLoading: false,
            showRuleModal: false,
            showDeleteModal: false,
            currencySymbol: null,
            allowProductRules: false,
            cartScope: this.discount.scope === DiscountScopes.CART,
            shippingScope: this.discount.scope === DiscountScopes.DELIVERY,
            considerAdvancedRules: this.discount.considerAdvancedRules,
            availableSetGroups: [],
            syncService: null,
            httpClient: null,
            sorterKeys: [],
            pickerKeys: [],
            restrictedRules: [],
        };
    },

    computed: {
        advancedPricesRepo() {
            return this.repositoryFactory.create('promotion_discount_prices');
        },

        repositoryGroups() {
            return this.repositoryFactory.create('promotion_setgroup');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        ruleFilter() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('conditions');
            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        currencyPriceColumns() {
            return [
                {
                    property: 'currency.translated.name',
                    label: this.$tc('sw-promotion.detail.main.discounts.pricesModal.labelCurrency'),
                },
                {
                    property: 'price',
                    dataIndex: 'price',
                    label: this.$tc('sw-promotion.detail.main.discounts.pricesModal.labelPrice'),
                },
            ];
        },

        scopes() {
            const scopes = [
                { key: DiscountScopes.CART, name: this.$tc('sw-promotion.detail.main.discounts.valueScopeCart') },
                { key: DiscountScopes.DELIVERY, name: this.$tc('sw-promotion.detail.main.discounts.valueScopeDelivery') },
                { key: DiscountScopes.SET, name: this.$tc('sw-promotion.detail.main.discounts.valueScopeSet') },
            ];

            let index = 1;
            this.availableSetGroups.forEach(() => {
                const keyValue = `${DiscountScopes.SETGROUP}-${index}`;
                const nameValue = `${this.$tc('sw-promotion.detail.main.discounts.valueScopeSetGroup')}-${index}`;
                scopes.push({ key: keyValue, name: nameValue });
                index += 1;
            });

            // if our groups are not yet loaded (async)
            // make sure that we have at least our selected entry visible
            // to avoid an accidental save with another value
            if (this.availableSetGroups.length <= 0) {
                const nameValue = `${this.$tc('sw-promotion.detail.main.discounts.valueScopeSetGroup')}`;
                scopes.push({ key: DiscountScopes.SETGROUP, name: nameValue });
            }

            return scopes;
        },

        types() {
            const availableTypes = [
                { key: DiscountTypes.ABSOLUTE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeAbsolute') },
                { key: DiscountTypes.PERCENTAGE, name: this.$tc('sw-promotion.detail.main.discounts.valueTypePercentage') },
                { key: DiscountTypes.FIXED_UNIT, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeFixedUnit') },
            ];

            // do not allow a fixed-total price for cart. this would mean the whole
            // cart is sold for price X.
            // we do only allow this option if the scope is something else
            if (!this.cartScope) {
                availableTypes.push(
                    { key: DiscountTypes.FIXED, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeFixed') },
                );
            }

            // if we do have a cart scope, only allow the fixed value if
            // at least advanced rules have been activated
            if (this.cartScope && this.discount.considerAdvancedRules) {
                availableTypes.push(
                    { key: DiscountTypes.FIXED, name: this.$tc('sw-promotion.detail.main.discounts.valueTypeFixed') },
                );
            }

            return availableTypes;
        },

        valueSuffix() {
            return discountHandler.getValueSuffix(this.discount.type, this.currencySymbol);
        },

        maxValueSuffix() {
            return this.currencySymbol;
        },

        showMaxValueSettings() {
            return this.discount.type === DiscountTypes.PERCENTAGE;
        },

        showAbsoluteAdvancedPricesSettings() {
            return (this.discount.type === DiscountTypes.ABSOLUTE || this.discount.type === DiscountTypes.FIXED);
        },

        // only show advanced max value settings if
        // at least a base max value has been set
        showMaxValueAdvancedPrices() {
            return this.discount.type === DiscountTypes.PERCENTAGE && this.discount.maxValue !== null;
        },

        maxValueAdvancedPricesTooltip() {
            if (
                this.discount.type === DiscountTypes.PERCENTAGE &&
                this.discount.maxValue !== null &&
                this.discount.promotionDiscountPrices.length > 0
            ) {
                return this.$tc('sw-promotion.detail.main.discounts.helpTextMaxValueAdvancedPrices');
            }
            return '';
        },

        isEditingDisabled() {
            if (!this.acl.can('promotion.editor')) {
                return true;
            }

            return !PromotionPermissions.isEditingAllowed(this.promotion);
        },

        displayAdvancedRuleOption() {
            return (this.discount.scope !== DiscountScopes.DELIVERY);
        },

        graduationSorters() {
            const result = [];

            this.sorterKeys.forEach((keyValue) => {
                result.push(
                    {
                        key: keyValue,
                        name: this.$tc(`sw-promotion-v2.detail.conditions.filter.sorter.${keyValue}`),
                    },
                );
            });

            return result;
        },

        graduationPickers() {
            const result = [];

            this.pickerKeys.forEach((keyValue) => {
                result.push(
                    {
                        key: keyValue,
                        name: this.$tc(`sw-promotion-v2.detail.conditions.filter.picker.${keyValue}`),
                    },
                );
            });

            return result;
        },

        isSetGroup() {
            const splittedScope = this.discount.scope.split('-');
            if (splittedScope[0] === DiscountScopes.SETGROUP) {
                return true;
            }
            return false;
        },

        isSet() {
            return (this.discount.scope === DiscountScopes.SET);
        },

        graduationAppliers() {
            const appliers = [
                {
                    key: 'ALL',
                    name: this.$tc('sw-promotion-v2.detail.conditions.filter.applier.ALL'),
                },
            ];

            // if selection is a setgroup and group is of type count, we reduce the standard maximum count
            // to the defined value of the group definitions
            let maxCount = 10;
            const splittedScope = this.discount.scope.split('-');
            if (splittedScope[0] === DiscountScopes.SETGROUP) {
                let i = 0;
                this.availableSetGroups.forEach((group) => {
                    i += 1;
                    if (i === parseInt(splittedScope[1], 10) && group.value < maxCount && group.packagerKey === 'COUNT') {
                        maxCount = group.value;
                    }
                });
            }

            let i;
            for (i = 1; i <= maxCount; i += 1) {
                appliers.push(
                    {
                        key: i,
                        name: this.$tc('sw-promotion-v2.detail.conditions.filter.applier.SELECT', 0, { count: i }),
                    },
                );
            }

            return appliers;
        },

        graduationCounts() {
            const counts = [
                {
                    key: 'ALL',
                    name: this.$tc('sw-promotion-v2.detail.conditions.filter.counter.ALL'),
                },
            ];

            let i;
            for (i = 1; i < 10; i += 1) {
                counts.push(
                    {
                        key: i,
                        name: this.$tc('sw-promotion-v2.detail.conditions.filter.counter.SELECT', 0, { count: i }),
                    },
                );
            }

            return counts;
        },

        isPickingModeVisible() {
            if (this.discount.scope.startsWith(DiscountScopes.SETGROUP)) {
                return true;
            }

            if (this.discount.scope === DiscountScopes.SET) {
                return true;
            }

            return false;
        },

        isMaxUsageVisible() {
            if (this.discount.scope === DiscountScopes.CART) {
                return false;
            }

            return true;
        },

        promotionDiscountSnippet() {
            return this.$tc(
                this.ruleConditionDataProviderService
                    .getAwarenessConfigurationByAssignmentName('promotionDiscounts').snippet,
                2,
            );
        },

    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
            this.syncService = Shopware.Service('syncService');
            this.httpClient = this.syncService.httpClient;

            this.currencyRepository.search(new Criteria(1, 25)).then((response) => {
                this.currencies = response;
                this.defaultCurrency = this.currencies.find(currency => currency.isSystemDefault);
                this.currencySymbol = this.defaultCurrency.symbol;
            });

            // remember the actual scope
            // our setgroup are loaded async.
            // so that would reset the dropdown to the first entry "cart".
            // this means we have to reset it after our loading
            const previousScope = this.discount.scope;

            this.loadSetGroups().then(() => {
                this.discount.scope = previousScope;
            });

            this.loadSorters().then((keys) => {
                this.sorterKeys = keys;
            });

            this.loadPickers().then((keys) => {
                this.pickerKeys = keys;
            });

            this.loadRestrictedRules();
        },

        onDiscountScopeChanged(value) {
            this.cartScope = (value === DiscountScopes.CART);
            this.shippingScope = (value === DiscountScopes.DELIVERY);

            if (value === DiscountScopes.DELIVERY) {
                this.discount.considerAdvancedRules = false;
            } else {
                this.discount.considerAdvancedRules = this.considerAdvancedRules;
            }

            // clear other values
            this.discount.pickerKey = '';

            // make sure to set to all, our cart doesn't have
            // this option and thus we make sure its set to default ALL
            this.discount.usageKey = 'ALL';

            // immediately select
            // a picker if none set for picker relevant scopes
            if (this.isPickingModeVisible) {
                this.discount.pickerKey = this.pickerKeys[0];
            }
        },

        // This function verifies the currently set value
        // depending on the discount type, and fixes it if
        // the min or maximum thresholds have been exceeded.
        onDiscountTypeChanged() {
            this.discount.value = discountHandler.getFixedValue(this.discount.value, this.discount.type);
        },

        onDiscountValueChanged(value) {
            this.discount.value = discountHandler.getFixedValue(value, this.discount.type);
        },

        // The number field does not allow a NULL input
        // so the value cannot be cleared anymore.
        // If the user removes the value, it will be 0 and converted
        // into NULL, which means no max value applies anymore.
        onMaxValueChanged(value) {
            if (value === 0) {
                // clear max value
                this.discount.maxValue = null;
                // clear any currency values if max value is gone
                this.clearAdvancedPrices();
            }
        },

        onClickAdvancedPrices() {
            this.currencies.forEach((currency) => {
                if (!this.isMemberOfCollection(currency)) {
                    // if we have a max-value setting active
                    // then our advanced prices is for this
                    // otherwise its for the promotion value itself
                    if (this.showMaxValueAdvancedPrices) {
                        this.prepareAdvancedPrices(currency, this.discount.maxValue);
                    } else {
                        this.prepareAdvancedPrices(currency, this.discount.value);
                    }
                }
            });
            this.displayAdvancedPrices = true;
        },

        prepareAdvancedPrices(currency, basePrice) {
            // first get the minimum value that is allowed
            let setPrice = discountHandler.getMinValue();
            // if basePrice is undefined take the minimum price
            if (basePrice !== undefined) {
                setPrice = basePrice;
            }
            // foreign currencies are translated at the exchange rate of the default currency
            setPrice *= currency.factor;
            // even if translated correctly the value may not be less than the allowed minimum value
            if (setPrice < discountHandler.getMinValue()) {
                setPrice = discountHandler.getMinValue();
            }
            // now create the value with the calculated and translated value
            const newAdvancedCurrencyPrices = this.advancedPricesRepo.create(Shopware.Context.api);
            newAdvancedCurrencyPrices.discountId = this.discount.id;
            newAdvancedCurrencyPrices.price = setPrice;
            newAdvancedCurrencyPrices.currencyId = currency.id;
            newAdvancedCurrencyPrices.currency = currency;

            this.discount.promotionDiscountPrices.add(newAdvancedCurrencyPrices);
        },

        clearAdvancedPrices() {
            const ids = this.discount.promotionDiscountPrices.getIds();
            let i;
            for (i = 0; i < ids.length; i += 1) {
                this.discount.promotionDiscountPrices.remove(ids[i]);
            }
        },

        isMemberOfCollection(currency) {
            let foundValue = false;
            const currencyID = currency.id;
            this.discount.promotionDiscountPrices.forEach((advancedPrice) => {
                if (advancedPrice.currencyId === currencyID) {
                    foundValue = true;
                    advancedPrice.currency = currency;
                }
            });
            return foundValue;
        },

        onCloseAdvancedPricesModal() {
            if ((this.discount.type === DiscountTypes.PERCENTAGE) && this.discount.maxValue === null) {
                // clear any currency values if max value is gone
                this.clearAdvancedPrices();
            } else {
                this.discount.promotionDiscountPrices.forEach((advancedPrice) => {
                    advancedPrice.price = discountHandler.getFixedValue(advancedPrice.price, DiscountTypes.ABSOLUTE);
                });
            }
            this.displayAdvancedPrices = false;
        },

        onShowDeleteModal() {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.onCloseDeleteModal();
            this.$nextTick(() => {
                this.$emit('discount-delete', this.discount);
            });
        },

        async loadSetGroups() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(
                Criteria.equals('promotionId', this.promotion.id),
            );

            await this.repositoryGroups.search(criteria).then((groups) => {
                this.availableSetGroups = groups;
            });

            return true;
        },

        async loadSorters() {
            return this.httpClient.get(
                '/_action/promotion/setgroup/sorter',
                {
                    headers: this.syncService.getBasicHeaders(),
                },
            ).then((response) => {
                return response.data;
            });
        },

        async loadPickers() {
            return this.httpClient.get(
                '/_action/promotion/discount/picker',
                {
                    headers: this.syncService.getBasicHeaders(),
                },
            ).then((response) => {
                return response.data;
            });
        },

        loadRestrictedRules() {
            this.ruleConditionDataProviderService.getRestrictedRules('promotionSetGroups')
                .then((result) => { this.restrictedRules = result; });
        },
    },
};
