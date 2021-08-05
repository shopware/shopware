import template from './sw-settings-customer-group-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-customer-group-list', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            sortBy: 'name',
            limit: 10,
            customerGroups: null,
            sortDirection: 'ASC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },

        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        allCustomerGroupsCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            this.customerGroupRepository.search(this.allCustomerGroupsCriteria)
                .then((searchResult) => {
                    this.total = searchResult.total;
                    this.customerGroups = searchResult;
                    this.isLoading = false;
                });
        },

        getColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-customer-group.list.columnName',
                inlineEdit: 'string',
                routerLink: 'sw.settings.customer.group.detail',
                primary: true,
            }, {
                property: 'displayGross',
                label: 'sw-settings-customer-group.list.columnDisplayGross',
                inlineEdit: 'boolean',
            }];
        },

        customerGroupCriteriaWithFilter(idsOfSelectedCustomerGroups) {
            const criteria = new Criteria();

            criteria.addFilter(
                Criteria.equalsAny('id', idsOfSelectedCustomerGroups),
            );

            return criteria;
        },

        createErrorNotification() {
            return this.createNotificationError({
                message: this.$tc('sw-settings-customer-group.notification.errorMessageCannotDeleteCustomerGroup'),
            });
        },

        customerGroupCanBeDeleted(customerGroup) {
            const hasNoCustomers = customerGroup.customers.length === 0;
            const hasNoSalesChannel = customerGroup.salesChannels.length === 0;

            return hasNoCustomers && hasNoSalesChannel;
        },

        deleteCustomerGroup(customerGroup) {
            this.$refs.listing.deleteId = null;

            if (!this.customerGroupCanBeDeleted(customerGroup)) {
                this.createErrorNotification();
            }

            this.customerGroupRepository.delete(customerGroup.id)
                .then(() => {
                    this.$refs.listing.resetSelection();
                    this.$refs.listing.doSearch();
                });
        },

        deleteCustomerGroups() {
            const selectedCustomerGroups = Object.values(this.$refs.listing.selection).map(currentProxy => {
                return currentProxy.id;
            });

            this.customerGroupRepository.search(this.customerGroupCriteriaWithFilter(selectedCustomerGroups))
                .then(response => {
                    const hasError = response.reduce((accumulator, customerGroup) => {
                        if (accumulator) {
                            return accumulator;
                        }

                        accumulator = !this.customerGroupCanBeDeleted(customerGroup);
                        return accumulator;
                    }, false);

                    if (hasError) {
                        this.createErrorNotification();
                    }

                    this.$refs.listing.deleteItems();
                });
        },

        onContextMenuDelete(customerGroup) {
            this.$refs.listing.deleteId = customerGroup.id;
        },
    },
});
