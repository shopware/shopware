import ProductFixture from '../service/administration/fixture/product.fixture';
import CustomerFixture from '../service/administration/fixture/customer.fixture';
import CategoryFixture from '../service/administration/fixture/category.fixture';
import ShippingFixture from '../service/administration/fixture/shipping.fixture';
import CmsFixture from '../service/administration/fixture/cms.fixture';
import OrderFixture from '../service/saleschannel/fixture/order.fixture';
import AdminSalesChannelFixture from '../service/administration/fixture/sales-channel.fixture';
import Fixture from '../service/administration/fixture.service';

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createDefaultFixture
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {Object} [options={}] - Options concerning deletion
 */
Cypress.Commands.add('createDefaultFixture', (endpoint, data = {}, jsonPath) => {
    const fixture = new Fixture();
    let finalRawData = {};

    if (!jsonPath) {
        jsonPath = endpoint;
    }

    return cy.fixture(jsonPath).then((json) => {
        finalRawData = Cypress._.merge(json, data);

        return fixture.create(endpoint, finalRawData);
    });
});

/**
 * Create product fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createProductFixture
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {Object} [options={}] - Options concerning creation
 */
Cypress.Commands.add('createProductFixture', (userData = {}) => {
    const fixture = new ProductFixture();

    return cy.fixture('product').then((result) => {
        return Cypress._.merge(result, userData);
    }).then((data) => {
        return fixture.setProductFixture(data);
    });
});

/**
 * Create product fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createCategoryFixture
 * @function
 * @param {object} [userData={}] - Additional category data
 */
Cypress.Commands.add('createCategoryFixture', (userData = {}) => {
    const fixture = new CategoryFixture();

    return cy.fixture('category').then((result) => {
        return Cypress._.merge(result, userData);
    }).then((data) => {
        return fixture.setCategoryFixture(data);
    });
});

/**
 * Create product fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createSalesChannelFixture
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {Object} [options={}] - Options concerning creation
 */
Cypress.Commands.add('createSalesChannelFixture', (userData = {}) => {
    const fixture = new AdminSalesChannelFixture();

    return cy.fixture('product').then((result) => {
        return Cypress._.merge(result, userData);
    }).then((data) => {
        return fixture.setSalesChannelFixture(data);
    });
});

/**
 * Create sales channel domain using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name setSalesChannelDomain
 * @function
 * @param {String} [salesChannelName=Storefront] - Options concerning creation
 */
Cypress.Commands.add('setSalesChannelDomain', (salesChannelName = 'Storefront') => {
    const fixture = new AdminSalesChannelFixture();
    return fixture.setSalesChannelDomain(salesChannelName)
});

/**
 * Create customer fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createCustomerFixture
 * @function
 * @param {Object} [userData={}] - Options concerning creation
 */
Cypress.Commands.add('createCustomerFixture', (userData = {}) => {
    const fixture = new CustomerFixture();
    let customerJson = null;

    return cy.fixture('customer').then((result) => {
        customerJson = Cypress._.merge(result, userData);
        return cy.fixture('customer-address');
    }).then((data) => {
        return fixture.setCustomerFixture(customerJson, data);
    });
});

/**
 * Create cms fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createCmsFixture
 * @function
 * @param {Object} [userData={}] - Options concerning creation
 */
Cypress.Commands.add('createCmsFixture', (userData = {}) => {
    const fixture = new CmsFixture();
    let pageJson = null;

    return cy.fixture('cms-page').then((data) => {
        pageJson = data;
        return cy.fixture('cms-section')
    }).then((data) => {
        return Cypress._.merge(pageJson, {
            sections: [data]
        });
    }).then((data) => {
        return fixture.setCmsPageFixture(data);
    });
});

/**
 * Create property fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createPropertyFixture
 * @function
 * @param {Object} [options={}] - Options concerning creation
 * @param {Object} [userData={}] - Options concerning creation
 */
Cypress.Commands.add('createPropertyFixture', (options, userData) => {
    let json = {};
    const fixture = new Fixture();

    return cy.fixture('property-group').then((result) => {
        json = Cypress._.merge(result, options);
    }).then(() => {
        return Cypress._.merge(json, userData);
    }).then((result) => {
        return fixture.create('property-group', result);
    });
});

/**
 * Create language fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createLanguageFixture
 * @function
 */
Cypress.Commands.add('createLanguageFixture', () => {
    let json = {};
    const fixture = new Fixture();

    return cy.fixture('language').then((result) => {
        json = result;

        return fixture.search('locale', {
            field: 'code',
            type: 'equals',
            value: 'en-PH'
        });
    }).then((result) => {
        return {
            name: json.name,
            localeId: result.id,
            parentId: json.parentId
        };
    }).then((result) => {
        return fixture.create('language', result);
    });
});

/**
 * Create shipping fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createShippingFixture
 * @function
 * @param {Object} [options={}] - Options concerning creation
 */
Cypress.Commands.add('createShippingFixture', (userData) => {
    const fixture = new ShippingFixture();

    return cy.fixture('shipping-method').then((result) => {
        return Cypress._.merge(result, userData);
    }).then((data) => {
        return fixture.setShippingFixture(data);
    });
});

/**
 * Create snippet fixture using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createSnippetFixture
 * @function
 * @param {Object} [options={}] - Options concerning creation
 */
Cypress.Commands.add('createSnippetFixture', () => {
    let json = {};
    const fixture = new Fixture();

    const findLanguageId = () => fixture.search('language', {
        type: 'equals',
        value: 'English'
    });
    const findSetId = () => fixture.search('snippet-set', {
        type: 'equals',
        value: 'BASE en-GB'
    });

    return cy.fixture('snippet')
        .then((result) => {
            json = result;

            return Promise.all([
                findLanguageId(),
                findSetId()
            ])
        })
        .then(([language, set]) => {
            return Cypress._.merge(json, {
                languageId: language.id,
                setId: set.id
            });
        })
        .then((result) => {
            return fixture.create('snippet', result);
        });
});

/**
 * Create guest order fixture
 * @memberOf Cypress.Chainable#
 * @name createGuestOrder
 * @function
 * @param {String} productId - Options concerning creation
 * @param {Object} [userData={}] - Options concerning creation
 */
Cypress.Commands.add('createGuestOrder', (productId, userData) => {
    const fixture = new OrderFixture();

    return cy.fixture('storefront-customer').then((result) => {
        return Cypress._.merge(result, userData);
    }).then((data) => {
        return fixture.createGuestOrder(productId, data);
    });
});

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name setToInitialState
 * @function
 */
Cypress.Commands.add('setToInitialState', () => {
    return cy.log('Cleaning, please wait a little bit.').then(() => {
        return cy.cleanUpPreviousState();
    }).then(() => {
        return cy.clearCacheAdminApi('DELETE', 'api/v1/_action/cache');
    }).then(() => {
        return cy.setLocaleToEnGb();
    });
});

/**
 * Sets category and visibility for a product in order to set it visible in the Storefront
 * @memberOf Cypress.Chainable#
 * @name setProductFixtureVisibility
 * @function
 */
Cypress.Commands.add('setProductFixtureVisibility', (productName, categoryName) => {
    let salesChannelId = '';
    let productId = '';

    return cy.searchViaAdminApi({
        endpoint: 'sales-channel',
        data: {
            field: 'name',
            value: 'Storefront'
        }
    }).then((result) => {
        salesChannelId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'product',
            data: {
                field: 'name',
                value: productName
            }
        });
    }).then((result) => {
        productId = result.id;

        return cy.updateViaAdminApi('product', productId, {
            data: {
                visibilities: [{
                    visibility: 30,
                    salesChannelId: salesChannelId,
                }]
            }
        });
    }).then(() => {
        return cy.searchViaAdminApi({
            endpoint: 'category',
            data: {
                field: 'name',
                value: categoryName
            }
        });
    }).then((result) => {
        return cy.updateViaAdminApi('product', productId, {
            data: {
                categories: [{
                    id: result.id
                }]
            }
        });
    });
});
