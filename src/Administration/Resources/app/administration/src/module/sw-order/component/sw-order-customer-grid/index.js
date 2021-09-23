import template from './sw-order-customer-grid.html.twig';
import './sw-order-customer-grid.scss';

const { Component, State, Service, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-order-customer-grid', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            customers: null,
            isLoading: false,
            isSwitchingCustomer: false,
            showNewCustomerModal: false,
            customer: {},
            disableRouteParams: true,
        };
    },

    computed: {
        customerRepository() {
            return Service('repositoryFactory').create('customer');
        },

        currencyRepository() {
            return Service('repositoryFactory').create('currency');
        },

        customerCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        customerCriterion() {
            const criteria = new Criteria();
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('salesChannel')
                .addAssociation('defaultPaymentMethod')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },

        customerColumns() {
            return [{
                property: 'select',
                label: '',
            }, {
                property: 'firstName',
                dataIndex: 'lastName,firstName',
                label: this.$tc('sw-order.createBase.customerGrid.columnCustomerName'),
                primary: true,
            }, {
                property: 'customerNumber',
                label: this.$tc('sw-order.createBase.customerGrid.columnCustomerNumber'),
            }, {
                property: 'email',
                label: this.$tc('sw-order.createBase.customerGrid.columnEmailAddress'),
            }];
        },

        showEmptyState() {
            return !this.total && !this.isLoading;
        },

        emptyTitle() {
            if (!this.term) {
                return this.$tc('sw-customer.list.messageEmpty');
            }

            return this.$tc('sw-order.createBase.customerGrid.textEmptySearch', 0, { name: this.term });
        },

        ...mapState('swOrder', ['cart']),
    },

    methods: {
        getList() {
            this.isLoading = true;
            this.customerRepository.search(this.customerCriteria).then(customers => {
                this.customers = customers;
                this.total = customers.total;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
        },

        isChecked(item) {
            return item.id === this.customer.id;
        },

        onCheckCustomer(item) {
            this.customer = item;
            this.handleSelectCustomer(item.id);
        },

        createCart(salesChannelId) {
            State.dispatch('swOrder/createCart', { salesChannelId });
        },

        setCustomer(customer) {
            State.dispatch('swOrder/selectExistingCustomer', { customer });
        },

        setCurrency(customer) {
            this.currencyRepository.get(customer.salesChannel.currencyId).then((currency) => {
                State.commit('swOrder/setCurrency', currency);
            });
        },

        handleSelectCustomer() {
            this.isSwitchingCustomer = true;

            return this.customerRepository.get(this.customer.id, Context.api, this.customerCriterion)
                .then(customer => {
                    if (!this.cart.token) {
                        this.createCart(customer.salesChannelId);
                    }

                    this.setCustomer(customer);
                    this.setCurrency(customer);
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-order.create.messageSwitchCustomerError'),
                    });
                }).finally(() => {
                    this.isSwitchingCustomer = false;
                });
        },
    },
});
