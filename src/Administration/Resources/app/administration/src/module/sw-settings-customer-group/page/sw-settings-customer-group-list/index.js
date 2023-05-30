import template from './sw-settings-customer-group-list.html.twig';

/**
 * @package customer-order
 */

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            searchConfigEntity: 'customer_group',
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

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.allCustomerGroupsCriteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            this.customerGroupRepository.search(criteria)
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
            const criteria = new Criteria(1, 25);

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
};
