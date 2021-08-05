import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';
import template from './sw-promotion-persona-form.html.twig';
import './sw-promotion-persona-form.scss';
import PersonaCustomerGridService from '../../service/persona-customer-grid.service';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
Component.register('sw-promotion-persona-form', {
    template,
    inject: ['repositoryFactory', 'acl'],

    props: {
        promotion: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            customerService: null,
            customerPersonaRepository: null,
            removeButtonDisabled: true,
            // PAGINATION
            gridCustomersPageDataSource: [],
            gridCustomersPageNr: 1,
            gridCustomersPageLimit: 10,
            customerModel: null,
        };
    },

    computed: {

        promotionRepository() {
            return this.repositoryFactory.create('promotion');
        },

        ruleFilter() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.multi('AND', [
                Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])]),
                Criteria.equalsAny('conditions.type', [
                    'customerBillingCountry', 'customerBillingStreet', 'customerBillingZipCode', 'customerIsNewCustomer',
                    'customerCustomerGroup', 'customerCustomerNumber', 'customerDaysSinceLastOrder',
                    'customerDifferentAddresses', 'customerLastName', 'customerOrderCount', 'customerShippingCountry',
                    'customerShippingStreet', 'customerShippingZipCode',
                ]),
            ]));

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        gridCustomersColumns() {
            if (this.customerService === null) {
                return [];
            }

            return this.customerService.getColumns();
        },

        gridCustomersTotalCount() {
            if (this.customerService === null) {
                return 0;
            }

            return this.customerService.getTotalCount();
        },

        gridCustomersItems() {
            return this.gridCustomersPageDataSource;
        },

        isRemoveButtonDisabled() {
            return this.removeButtonDisabled;
        },

        customerCriteria() {
            return new Criteria();
        },

        isEditingDisabled() {
            if (!this.acl.can('promotion.editor')) {
                return true;
            }

            return !PromotionPermissions.isEditingAllowed(this.promotion);
        },
    },
    watch: {
        promotion() {
            if (this.promotion) {
                // as soon as our promotion has a value
                // we load our real data (async handling)
                this.createdComponent();
            }
        },
    },
    created() {
        if (this.promotion) {
            this.createdComponent();
        }
    },
    methods: {
        createdComponent() {
            // fetch our promotion which is necessary
            // to create our many-to-many repository fro the customer persona
            // inside the "then" function.
            // we create our actual repository for the
            // promotion-customer many-to-many
            this.customerPersonaRepository = this.repositoryFactory.create(
                this.promotion.personaCustomers.entity,
                this.promotion.personaCustomers.source,
            );

            // create our customer grid object
            // that handles logic for the grid and persona customer assignments
            this.customerService = new PersonaCustomerGridService(
                this,
                this.repositoryFactory.create('customer'),
                this.customerPersonaRepository,
                Shopware.Context.api,
            );

            this.customerService.reloadCustomers().then(() => {
                this.refreshGridDataSource();
                this.updateStateVariables();
            });
        },

        // -------------------------------------------------------------------------------------------------------
        // CUSTOMER GRID EVENTS
        // -------------------------------------------------------------------------------------------------------
        // adds the provided customer to the
        // persona list and updates the grid
        onAddCustomer(id, item) {
            // somehow also null is being passed on
            // "all the time". to avoid circular references and
            // exceeding call stacks, we check for null
            if (item == null) {
                return;
            }

            this.customerService.addCustomer(item.id, Shopware.Context.api).then(() => {
                // remove from vue search field
                // and make it empty for the next searches
                this.$refs.selectCustomerSearch.clearSelection();
                // also refresh our grid
                this.refreshGridDataSource();
                this.updateStateVariables();
            });
        },

        // removes an assigned customer from the
        // persona list and updates the grid
        onRemoveCustomer(customer) {
            this.customerService.removeCustomer(customer).then(() => {
                // refresh our grid
                this.refreshGridDataSource();
                this.updateStateVariables();
            });
        },

        onRemoveSelectedCustomers() {
            const promiseList = [];

            // remove all our selected customers from our grid
            const selection = this.$refs.gridCustomers.selection;
            Object.values(selection).forEach(customer => {
                promiseList.push(this.customerService.removeCustomer(customer));
            });

            Promise.all(promiseList).then(() => {
                this.refreshGridDataSource();
                this.updateStateVariables();
            });
        },
        onCustomerPageChange(data) {
            // assign new pagination status data
            this.gridCustomersPageNr = data.page;
            this.gridCustomersPageLimit = data.limit;

            this.refreshGridDataSource();
            this.updateStateVariables();
        },
        onGridSelectionChanged(selection, selectionCount) {
            // enable our button if rows have been selected.
            // disable our delete button if nothing has been selected
            this.removeButtonDisabled = selectionCount <= 0;
        },
        refreshGridDataSource() {
            this.gridCustomersPageDataSource = this.customerService.getPageDataSource(
                this.gridCustomersPageNr,
                this.gridCustomersPageLimit,
            );

            // if we have no data on the current page
            // but still a total count, then this means
            // that we are on a page that has been removed due to removing some customers.
            // so just try to reduce the page and refresh again
            if (this.gridCustomersTotalCount > 0 && this.gridCustomersPageDataSource.length <= 0) {
                // decrease, but stick with minimum of 1
                this.gridCustomersPageNr = (this.gridCustomersPageNr === 1) ? 1 : this.gridCustomersPageNr -= 1;
                this.refreshGridDataSource();
            }
        },
        updateStateVariables() {
            // assign our data to our promotion state.
            // this one will be saved later on
            Shopware.State.commit('swPromotionDetail/setPersonaCustomerIdsAdd', this.customerService.getCustomerIdsToAdd());
            Shopware.State.commit(
                'swPromotionDetail/setPersonaCustomerIdsDelete',
                this.customerService.getCustomerIdsToDelete(),
            );
        },
    },
});
