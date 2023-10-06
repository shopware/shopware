/*
 * @package inventory
 */

import template from './sw-product-variants-configurator-prices.html.twig';
import './sw-product-variants-configurator-prices.scss';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        product: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            activeGroup: {},
            term: '',
            optionsForGroup: [],
            currencies: {},
            isLoading: true,
        };
    },

    computed: {
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        currenciesList() {
            return this.currencies.map((currency) => {
                return {
                    id: currency.id,
                    name: currency.name,
                    symbol: currency.symbol,
                };
            });
        },

        optionColumns() {
            const defaultColumns = [
                {
                    property: 'name',
                    label: this.$tc('sw-product.variations.configuratorModal.priceOptions'),
                    rawData: true,
                },
            ];

            const currenciesColumns = this.currenciesList.map((currency) => {
                return {
                    property: `currency.${currency.id}`,
                    label: currency.name,
                    rawData: true,
                    allowResize: true,
                    width: '200px',
                };
            });

            return [...defaultColumns, ...currenciesColumns];
        },
    },

    watch: {
        'activeGroup'() {
            this.getOptionsForGroup();
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        onSearchTermChange() {
            this.getOptionsForGroup();
        },
        mountedComponent() {
            this.isLoading = false;
            this.loadCurrencies();
        },

        loadCurrencies() {
            this.currencyRepository
                .search(new Criteria(1, 25))
                .then((searchResult) => {
                    this.currencies = searchResult;
                });
        },

        getOptionsForGroup() {
            this.optionsForGroup = this.product.configuratorSettings
                // Filter if option is in active group
                .filter((element) => {
                    if (element.option.groupId === this.activeGroup.id) {
                        this.resetSurcharges(element);
                        return true;
                    }
                    return false;
                })
                // Filter if search term matches option name
                .filter((element) => element.option.translated.name.toLowerCase().includes(this.term.toLowerCase()));
        },

        resetSurcharges(option, force = false) {
            // check if surcharge exists
            if (Array.isArray(option.price) && option.price && option.price.length > 0 && !force) {
                return;
            }

            // set empty surcharge
            this.$set(option, 'price', []);
            this.currenciesList.forEach((currency) => {
                if (!option.price.find(price => price.currencyId === currency.id)) {
                    const newPriceForCurrency = {
                        currencyId: currency.id,
                        gross: 0,
                        linked: true,
                        net: 0,
                    };
                    option.price.push(newPriceForCurrency);
                }
            });
        },

        getCurrencyOfOption(option, currencyId) {
            return option.price.find((currency) => currency.currencyId === currencyId);
        },
    },
};
