import template from './sw-order-create-options.html.twig';
import './sw-order-create-options.scss';

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-order-create-options', {
    template,

    inject: ['repositoryFactory'],

    props: {
        promotionCodes: {
            type: Array,
            required: true,
        },

        disabledAutoPromotions: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            promotionCodeTags: this.promotionCodes,
            customerAddresses: [],
            isLoadingBillingAddress: false,
            isLoadingShippingAddress: false,
            addressSearchTerm: '',
        };
    },

    computed: {
        context: {
            get() {
                return this.customer ? this.customer.salesChannel : {};
            },

            set(context) {
                if (this.customer) this.customer.salesChannel = context;
            },
        },

        salesChannelId: {
            get() {
                return this.customer ? this.customer.salesChannelId : null;
            },

            set(salesChannelId) {
                if (this.customer) this.customer.salesChannelId = salesChannelId;
            },
        },

        testOrder: {
            get() {
                return State.get('swOrder').testOrder;
            },

            set(testOrder) {
                State.commit('swOrder/setTestOrder', testOrder);
            },
        },

        salesChannelCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        paymentMethodCriteria() {
            const criteria = new Criteria();

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            criteria.addFilter(Criteria.equals('afterOrderEnabled', 1));

            return criteria;
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        billingAddressId: {
            get() {
                if (this.customer) {
                    const { billingAddressId, defaultBillingAddressId } = this.customer;
                    return billingAddressId || defaultBillingAddressId;
                }

                return null;
            },

            set(billingAddressId) {
                if (this.customer) this.customer.billingAddressId = billingAddressId;
            },
        },

        shippingAddressId: {
            get() {
                if (this.customer) {
                    const { shippingAddressId, defaultShippingAddressId } = this.customer;
                    return shippingAddressId || defaultShippingAddressId;
                }

                return null;
            },

            set(shippingAddressId) {
                if (this.customer) this.customer.shippingAddressId = shippingAddressId;
            },
        },

        addressRepository() {
            return this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source,
            );
        },

        addressCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            if (this.addressSearchTerm) {
                criteria.setTerm(this.addressSearchTerm);
            }

            return criteria;
        },

        ...mapState('swOrder', ['currency', 'customer', 'defaultSalesChannel']),
        ...mapGetters('swOrder', ['isCartTokenAvailable', 'currencyId']),
    },

    watch: {
        'context.currencyId': {
            handler() {
                this.getCurrency();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getCustomerAddresses();
        },

        getCurrency() {
            return this.currencyRepository.get(this.context.currencyId).then((currency) => {
                State.commit('swOrder/setCurrency', currency);
            });
        },

        getCustomerAddress(address) {
            if (!address) return '';

            const result = [];

            const properties = ['street', 'zipcode', 'city', 'countryState', 'country'];

            properties.forEach(property => {
                if (!address[property]) return;

                if (property === 'countryState' || property === 'country') {
                    result.push(address[property].translated?.name);
                    return;
                }

                result.push(address[property]);
            });

            return result.join(', ');
        },

        validatePromotions(searchTerm) {
            if (searchTerm.length < 0) {
                return false;
            }

            const isExist = this.promotionCodes.find(code => code === searchTerm);

            if (isExist) {
                return false;
            }

            return searchTerm;
        },

        changeShippingAddress(value) {
            const address = this.customerAddresses.find(item => item.id === value);

            this.context = {
                ...this.context,
                shippingAddressId: value,
                shippingAddress: address,
            };
        },

        changeBillingAddress(value) {
            const address = this.customerAddresses.find(item => item.id === value);

            this.context = {
                ...this.context,
                billingAddressId: value,
                billingAddress: address,
            };
        },

        toggleAutoPromotions(value) {
            this.$emit('auto-promotions-toggle', value);
        },

        changePromotionCodes(value) {
            this.$emit('promotions-change', value);
        },

        getCustomerAddresses() {
            this.isLoadingBillingAddress = true;
            this.isLoadingShippingAddress = true;

            // Get the latest addresses from customer's db
            return this.addressRepository.search(this.addressCriteria)
                .then((addresses) => {
                    this.customerAddresses = addresses;
                }).finally(() => {
                    this.isLoadingBillingAddress = false;
                    this.isLoadingShippingAddress = false;
                });
        },

        searchBillingAddress(searchTerm) {
            this.isLoadingBillingAddress = true;

            this.addressSearchTerm = searchTerm;

            return this.addressRepository.search(this.addressCriteria)
                .then((addresses) => {
                    this.$refs.billingAddress.results = addresses;
                }).finally(() => {
                    this.isLoadingBillingAddress = false;
                });
        },

        searchShippingAddress(searchTerm) {
            this.isLoadingShippingAddress = true;

            this.addressSearchTerm = searchTerm;

            return this.addressRepository.search(this.addressCriteria)
                .then((addresses) => {
                    this.$refs.shippingAddress.results = addresses;
                }).finally(() => {
                    this.isLoadingShippingAddress = false;
                });
        },

        setSameAddress(sourceAddressId, targetAddress) {
            const address = this.customerAddresses.find(item => item.id === this[sourceAddressId]);
            this.$refs[targetAddress].setValue(address);
        },
    },
});
