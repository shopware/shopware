import template from './sw-order-customer-grid.html.twig';
import './sw-order-customer-grid.scss';

const { State, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

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
        customerData() {
            return State.get('swOrder').customer;
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        customerCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        customerCriterion() {
            const criteria = new Criteria(1, 25);
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
                label: this.$tc('sw-order.initialModal.customerGrid.columnCustomerName'),
                primary: true,
            }, {
                property: 'customerNumber',
                label: this.$tc('sw-order.initialModal.customerGrid.columnCustomerNumber'),
            }, {
                property: 'email',
                label: this.$tc('sw-order.initialModal.customerGrid.columnEmailAddress'),
            }];
        },

        showEmptyState() {
            return !this.total && !this.isLoading;
        },

        emptyTitle() {
            if (!this.term) {
                return this.$tc('sw-customer.list.messageEmpty');
            }

            return this.$tc('sw-order.initialModal.customerGrid.textEmptySearch', 0, { name: this.term });
        },

        ...mapState('swOrder', ['cart']),
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            if (!this.customerData) {
                return;
            }

            this.$refs.customerFilter.term = this.customerData?.customerNumber;
            this.onCheckCustomer(this.customerData);
        },

        getList() {
            this.isLoading = true;
            return this.customerRepository.search(this.customerCriteria).then(customers => {
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
            return State.dispatch('swOrder/createCart', { salesChannelId });
        },

        setCustomer(customer) {
            State.dispatch('swOrder/selectExistingCustomer', { customer });
        },

        setCurrency(customer) {
            return this.currencyRepository.get(customer.salesChannel.currencyId).then((currency) => {
                State.commit('swOrder/setCurrency', currency);
            });
        },

        async handleSelectCustomer(customerId) {
            this.isSwitchingCustomer = true;

            try {
                const customer = await this.customerRepository.get(customerId, Context.api, this.customerCriterion);

                if (!this.cart.token) {
                    // It is compulsory to create cart and get cart token first
                    await this.createCart(customer.salesChannelId);
                }

                this.customer = customer;
                this.setCustomer(customer);
                this.setCurrency(customer);

                await this.updateCustomerContext();
            } catch {
                this.createNotificationError({
                    message: this.$tc('sw-order.create.messageSwitchCustomerError'),
                });
            } finally {
                this.isSwitchingCustomer = false;
            }
        },

        onAddNewCustomer(customerId) {
            if (!customerId) {
                return;
            }

            // Refresh customer list if new customer is created successfully
            this.getList();
            this.page = 1;
            this.term = '';
        },

        updateCustomerContext() {
            return State.dispatch('swOrder/updateCustomerContext', {
                customerId: this.customer.id,
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            }).then((response) => {
                // Update cart after customer context is updated
                if (response.status === 200) {
                    this.getCart();
                }
            });
        },

        getCart() {
            return State.dispatch('swOrder/getCart', {
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            });
        },
    },
};
