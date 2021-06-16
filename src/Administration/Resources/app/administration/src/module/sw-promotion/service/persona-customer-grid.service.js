const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 */
export default class PersonaCustomerGridService {
    constructor(component, repoCustomers, repoPromotionCustomers, context) {
        this.component = component;
        this.repoCustomers = repoCustomers;
        this.repoPromotionCustomers = repoPromotionCustomers;
        this.context = context;

        this.dataSource = [];
        this.addIds = [];
        this.deleteIds = [];
    }

    // Gets the ready to use and translated
    // columns for the persona customer grid.
    getColumns() {
        return [{
            property: 'fullName',
            dataIndex: 'fullName',
            label: this.component.$tc('sw-promotion.detail.main.preconditions.persona.customers.grid.headerName'),
            allowResize: false,
        }, {
            property: 'customerNumber',
            dataIndex: 'customerNumber',
            label: this.component.$tc('sw-promotion.detail.main.preconditions.persona.customers.grid.headerCustomerNumber'),
            allowResize: false,
        }];
    }

    async reloadCustomers() {
        const criteria = new Criteria();

        // search all customer persona entries and load them
        // into our customer list which will be shown using our vue grid.
        await this.repoPromotionCustomers.search(criteria, this.context).then((customers) => {
            this.dataSource = customers;
        });
    }

    getTotalCount() {
        return this.dataSource.length;
    }

    getPageDataSource(pageNr, pageLimit) {
        if (pageNr < 1) {
            pageNr = 1;
        }

        const offset = (pageNr - 1) * pageLimit;
        return this.dataSource.slice(offset, offset + pageLimit);
    }

    getCustomerIdsToAdd() {
        return this.addIds;
    }

    getCustomerIdsToDelete() {
        return this.deleteIds;
    }

    // this function will be called whenever a
    // user selects a new customer from the dropdown search.
    // it will look for this customer entity and
    // add it as persona, if not already added.
    async addCustomer(customerId, context) {
        // load our customer entity
        // for the provided customer Id
        await this.repoCustomers
            .get(customerId, context)
            .then((customer) => {
                // check if its already added in our list.
                // if the customer is not existing, then
                // push it to the local list.
                if (!isInDataSource(this.dataSource, customer.id)) {
                    // add to data source
                    this.dataSource.push(customer);
                    // add to our delta ADD list
                    this.addIds.push(customer.id);
                }
            });
    }

    // Removes the provided customer from the
    // assigned persona customer list of
    // the promotion.
    async removeCustomer(customer) {
        // add to our delta DELETE list
        await this.deleteIds.push(customer.id);
        // remove from the local data source
        this.dataSource = this.dataSource.filter((c) => {
            return c.id !== customer.id;
        });
    }
}

// Gets if the provided customer id
// is already in the list of persona customers
// within the current local scope (not in db).
function isInDataSource(datasource, customerId) {
    for (let i = 0; i < datasource.length; i += 1) {
        const customer = datasource[i];
        // check if customer id already in list
        if (customer.id === customerId) {
            return true;
        }
    }

    return false;
}
