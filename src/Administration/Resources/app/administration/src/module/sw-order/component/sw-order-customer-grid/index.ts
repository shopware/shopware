import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from '../../../../core/data/repository.data';

import template from './sw-order-customer-grid.html.twig';
import './sw-order-customer-grid.scss';

import type { Cart } from '../../order.types';

/**
 * @package checkout
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
        showSalesChannelSelectModal: boolean,
        showCustomerChangesModal: boolean,
        salesChannelIds: string[],
        customerDraft: Entity<'customer'>|null,
        } {
        return {
            customers: null,
            isLoading: false,
            isSwitchingCustomer: false,
            showNewCustomerModal: false,
            customer: null,
            customerDraft: null,
            disableRouteParams: true,
            showSalesChannelSelectModal: false,
            showCustomerChangesModal: false,
            salesChannelIds: [],
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
            criteria.addAssociation('boundSalesChannel');
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
                .addAssociation('salesChannel.languages')
                .addAssociation('defaultPaymentMethod')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags')
                .addAssociation('boundSalesChannel');

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

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        salesChannelRepository(): RepositoryType<'sales_channel'> {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria(): CriteriaType {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('active', true));

            if (this.customer?.boundSalesChannelId) {
                criteria.addFilter(Criteria.equals('id', this.customer.boundSalesChannelId));
            }

            return criteria;
        },

        isSelectSalesChannelDisabled(): boolean {
            if (!this.customer?.salesChannelId) {
                return true;
            }

            return !this.salesChannelIds.includes(this.customer.salesChannelId);
        },
    },

    mounted() {
        void this.mountedComponent();
    },

    methods: {
        async mountedComponent(): Promise<void> {
            this.salesChannelIds = await this.loadSalesChannel();

            if (!this.customerData) {
                return;
            }

            // @ts-expect-error
            this.$refs.customerFilter.term = this.customerData?.customerNumber;
            void this.onCheckCustomer(this.customerData);
        },

        getList(): Promise<void> {
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

        async onCheckCustomer(item: Entity<'customer'>) {
            // If there's an existing customer, save it as a draft.
            if (this.customer) {
                this.customerDraft = this.customer;
            }

            this.customer = await this.customerRepository
                .get(item.id, Context.api, this.customerCriterion);

            const isExists = (this.customer?.salesChannel?.languages || []).some(
                (language) => language.id === Context.api.systemLanguageId,
            );

            if (!isExists && this.customer?.salesChannel?.languageId) {
                State.commit('context/setLanguageId', this.customer.salesChannel.languageId);
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (isExists && !State.getters['context/isSystemDefaultLanguage']) {
                State.commit('context/resetLanguageToDefault');
            }

            // If the customer belongs to a sales channel not in the allowed list and has no bound sales channel.
            if (!this.customer?.boundSalesChannelId) {
                this.showSalesChannelSelectModal = true;

                return;
            }

            // If switching to a different customer whose sales channel is different from the current one.
            if (
                this.customerDraft &&
                this.customer?.boundSalesChannelId &&
                this.customerDraft.salesChannelId !== this.customer.boundSalesChannelId
            ) {
                this.showCustomerChangesModal = true;

                return;
            }

            void this.handleSelectCustomer();
        },

        createCart(salesChannelId: string): Promise<void> {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return State.dispatch('swOrder/createCart', { salesChannelId });
        },

        setCustomer(customer: Entity<'customer'>|null): void {
            void State.dispatch('swOrder/selectExistingCustomer', { customer });
        },

        async handleSelectCustomer(): Promise<void> {
            this.isSwitchingCustomer = true;

            try {
                if (!this.cart.token) {
                    // It is compulsory to create cart and get cart token first
                    await this.createCart(this.customer?.salesChannelId ?? '');
                }

                this.setCustomer(this.customer);

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
            this.page = 1;
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

        async loadSalesChannel(): Promise<string[]> {
            const { data: ids } = await this.salesChannelRepository.searchIds(this.salesChannelCriteria);

            return ids;
        },

        onSalesChannelChange(salesChannelId: string): void {
            if (!this.customer) {
                return;
            }

            this.customer.salesChannelId = salesChannelId;
        },

        onCloseSalesChannelSelectModal() {
            this.customer = this.customerDraft;

            this.showSalesChannelSelectModal = false;
        },

        async onSelectSalesChannel() {
            this.isLoading = true;

            try {
                await this.handleSelectCustomer();
            } finally {
                this.isLoading = false;
                this.showSalesChannelSelectModal = false;
            }
        },

        customerUnavailable(customer: Entity<'customer'>): boolean {
            if (!this.salesChannelIds.length) {
                return true;
            }

            return !!customer?.boundSalesChannelId && !this.salesChannelIds.includes(customer.boundSalesChannelId);
        },

        async onChangeCustomer() {
            this.isLoading = true;

            try {
                await this.handleSelectCustomer();
            } finally {
                this.isLoading = false;
                this.showCustomerChangesModal = false;
            }
        },

        onCloseCustomerChangesModal() {
            this.customer = this.customerDraft;

            this.showCustomerChangesModal = false;
        },
    },
});
