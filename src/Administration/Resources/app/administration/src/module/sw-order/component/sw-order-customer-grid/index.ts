import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from '../../../../core/data/repository.data';

import template from './sw-order-customer-grid.html.twig';
import './sw-order-customer-grid.scss';

import type { Cart } from '../../order.types';

/**
 * @package customer-order
 */

const { Component, State, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

interface GridColumn {
    property: string,
    dataIndex?: string,
    label: string,
    primary?: boolean,
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data(): {
        customers: EntityCollection<'customer'>|null,
        isLoading: boolean,
        isSwitchingCustomer: boolean,
        showNewCustomerModal: boolean,
        customer: Entity<'customer'>|null,
        disableRouteParams: boolean,
        } {
        return {
            customers: null,
            isLoading: false,
            isSwitchingCustomer: false,
            showNewCustomerModal: false,
            customer: null,
            disableRouteParams: true,
        };
    },

    computed: {
        customerData(): Entity<'customer'>| null {
            return State.get('swOrder').customer;
        },

        customerRepository(): RepositoryType<'customer'> {
            return this.repositoryFactory.create('customer');
        },

        customerCriteria(): CriteriaType {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('salesChannel');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.term) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                criteria.setTerm(this.term);
            }

            return criteria;
        },

        customerCriterion(): CriteriaType {
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

        customerColumns(): GridColumn[] {
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
            },
            {
                property: 'salesChannel',
                label: this.$tc('sw-order.initialModal.customerGrid.columnSalesChannel'),
            }, {
                property: 'email',
                label: this.$tc('sw-order.initialModal.customerGrid.columnEmailAddress'),
            }];
        },

        showEmptyState(): boolean {
            return !this.total && !this.isLoading;
        },

        emptyTitle(): string {
            if (!this.term) {
                return this.$tc('sw-customer.list.messageEmpty');
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            return this.$tc('sw-order.initialModal.customerGrid.textEmptySearch', 0, { name: this.term });
        },

        cart(): Cart {
            return State.get('swOrder').cart;
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent(): void {
            if (!this.customerData) {
                return;
            }

            // @ts-expect-error
            this.$refs.customerFilter.term = this.customerData?.customerNumber;
            this.onCheckCustomer(this.customerData);
        },

        getList() {
            this.isLoading = true;
            return this.customerRepository.search(this.customerCriteria)
                .then((customers) => {
                    this.customers = customers;
                    // @ts-expect-error
                    this.total = customers.total;
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
        },

        isChecked(item: Entity<'customer'>): boolean {
            return item.id === this.customer?.id;
        },

        onCheckCustomer(item: Entity<'customer'>): void {
            this.customer = item;
            void this.handleSelectCustomer(item.id);
        },

        createCart(salesChannelId: string): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/createCart', { salesChannelId });
        },

        setCustomer(customer: Entity<'customer'>|null): void {
            void State.dispatch('swOrder/selectExistingCustomer', { customer });
        },

        async handleSelectCustomer(customerId: string): Promise<void> {
            this.isSwitchingCustomer = true;

            try {
                const customer = await this.customerRepository
                    .get(customerId, Context.api, this.customerCriterion);

                if (!this.cart.token) {
                    // It is compulsory to create cart and get cart token first
                    await this.createCart(customer?.salesChannelId ?? '');
                }

                this.customer = customer;
                this.setCustomer(customer);

                await this.updateCustomerContext();
            } catch {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.createNotificationError({
                    message: this.$tc('sw-order.create.messageSwitchCustomerError'),
                });
            } finally {
                this.isSwitchingCustomer = false;
            }
        },

        onAddNewCustomer(customerId: string): void {
            if (!customerId) {
                return;
            }

            // Refresh customer list if new customer is created successfully
            void this.getList();
            // @ts-expect-error
            this.page = 1;
            // @ts-expect-error
            this.term = '';
        },

        updateCustomerContext(): Promise<void> {
            return State.dispatch('swOrder/updateCustomerContext', {
                customerId: this.customer?.id,
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
            }).then((response) => {
                // Update cart after customer context is updated
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                if (response.status === 200) {
                    void this.getCart();
                }
            });
        },

        getCart(): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/getCart', {
                salesChannelId: this.customer?.salesChannelId,
                contextToken: this.cart.token,
            });
        },
    },
});
