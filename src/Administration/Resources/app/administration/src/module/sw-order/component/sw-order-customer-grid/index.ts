import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from '../../../../core/data/repository.data';

import template from './sw-order-customer-grid.html.twig';
import './sw-order-customer-grid.scss';

import type { Cart } from '../../order.types';

/**
 * @sw-package checkout
 */

const { Component, Store, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

interface GridColumn {
    property: string;
    dataIndex?: string;
    label: string;
    primary?: boolean;
}

interface CustomerFilterRef {
    term: string;
}

type ApiErrorResponse = {
    response?: {
        data?: {
            errors?: Array<{
                code?: string;
            }>;
        };
    };
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data(): {
        customers: EntityCollection<'customer'> | null;
        isLoading: boolean;
        isSwitchingCustomer: boolean;
        showNewCustomerModal: boolean;
        customer: Entity<'customer'> | null;
        disableRouteParams: boolean;
        showSalesChannelSelectModal: boolean;
        showCustomerChangesModal: boolean;
        salesChannelIds: string[];
        customerDraft: Entity<'customer'> | null;
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
        customerData(): Entity<'customer'> | null {
            return Store.get('swOrder').customer;
        },

        customerRepository(): RepositoryType<'customer'> {
            return this.repositoryFactory.create('customer');
        },

        customerCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('boundSalesChannel');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            if (this.term) {
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
            return [
                {
                    property: 'select',
                    label: '',
                },
                {
                    property: 'firstName',
                    dataIndex: 'lastName,firstName',
                    label: this.$t('sw-order.initialModal.customerGrid.columnCustomerName'),
                    primary: true,
                },
                {
                    property: 'customerNumber',
                    label: this.$t('sw-order.initialModal.customerGrid.columnCustomerNumber'),
                },
                {
                    property: 'salesChannel',
                    label: this.$t('sw-order.initialModal.customerGrid.columnSalesChannel'),
                },
                {
                    property: 'email',
                    label: this.$t('sw-order.initialModal.customerGrid.columnEmailAddress'),
                },
            ];
        },

        showEmptyState(): boolean {
            return !this.total && !this.isLoading;
        },

        emptyTitle(): string {
            if (!this.term) {
                return this.$t('sw-customer.list.messageEmpty');
            }

            return this.$t('sw-order.initialModal.customerGrid.textEmptySearch', { name: this.term }, 0);
        },

        cart(): Cart {
            return Store.get('swOrder').cart;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        salesChannelRepository(): RepositoryType<'sales_channel'> {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria(): CriteriaType {
            const criteria = new Criteria();
            criteria.addAssociation('languages');
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

            const customerNumber = this.customerData.customerNumber ?? '';

            const customerFilter = this.$refs.customerFilter as CustomerFilterRef | undefined;
            if (customerFilter) {
                customerFilter.term = customerNumber;
            }

            void this.onSearch(customerNumber);
            void this.onCheckCustomer(this.customerData);
        },

        getList(): Promise<void> {
            this.isLoading = true;
            return this.customerRepository
                .search(this.customerCriteria)
                .then((customers) => {
                    this.customers = customers;
                    // @ts-expect-error
                    this.total = customers.total;
                })
                .finally(() => {
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

            this.customer = await this.customerRepository.get(item.id, Context.api, this.customerCriterion);

            this.checkContextLanguage();

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
            return Store.get('swOrder').createCart({ salesChannelId });
        },

        setCustomer(customer: Entity<'customer'> | null): void {
            void Store.get('swOrder').selectExistingCustomer({ customer });
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
            } catch (error) {
                let message = this.$t('sw-order.create.messageSwitchCustomerError');
                const errorCode = (error as ApiErrorResponse).response?.data?.errors?.[0]?.code;

                if (errorCode) {
                    const messageKey = `global.error-codes.${errorCode}`;
                    const translatedMessage = this.$t(messageKey);

                    if (translatedMessage !== messageKey) {
                        message = `${message}: ${translatedMessage}`;
                    }
                }

                this.createNotificationError({
                    message,
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

        async updateCustomerContext(): Promise<void> {
            if (!this.customer) return;

            await Store.get('swOrder')
                .updateCustomerContext({
                    customerId: this.customer.id,
                    salesChannelId: this.customer.salesChannelId,
                    contextToken: this.cart.token,
                })
                .then((response) => {
                    // Update cart after customer context is updated
                    if (response.status === 200) {
                        void this.getCart();
                    }
                });
        },

        async getCart(): Promise<void> {
            if (!this.customer) return;

            await Store.get('swOrder').getCart({
                salesChannelId: this.customer.salesChannelId,
                contextToken: this.cart.token,
            });
        },

        async loadSalesChannel(): Promise<string[]> {
            const { data: ids } = await this.salesChannelRepository.searchIds(this.salesChannelCriteria);

            return ids;
        },

        onSalesChannelChange(salesChannelId: string, salesChannel: Entity<'sales_channel'>): void {
            if (!this.customer) {
                return;
            }

            this.customer.salesChannelId = salesChannelId;
            this.customer.salesChannel = salesChannel;

            this.checkContextLanguage();
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

        checkContextLanguage() {
            const exists = (this.customer?.salesChannel?.languages || []).some(
                (language) => language.id === Context.api.systemLanguageId,
            );

            if (!exists && this.customer?.salesChannel?.languageId) {
                Store.get('context').api.languageId = this.customer.salesChannel.languageId;
            }

            if (exists && !Store.get('context').isSystemDefaultLanguage) {
                Store.get('context').resetLanguageToDefault();
            }
        },
    },
});
