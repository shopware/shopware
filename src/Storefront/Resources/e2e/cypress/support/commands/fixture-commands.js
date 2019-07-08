const _ = require('lodash');
const uuid = require('uuid/v4');

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createDefaultFixture
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {Object} [options={}] - Options concerning deletion
 */
Cypress.Commands.add('createDefaultFixture', (endpoint) => {
    return cy.fixture(endpoint).then((json) => {
        return cy.createViaAdminApi({
            endpoint: endpoint,
            data: json
        });
    });
});

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name removeFixtureByName
 * @function
 * @param {String} name - Name of the fixture to be deleted
 * @param {String} endpoint - API endpoint for the request
 * @param {Object} [options={}] - Options concerning deletion [options={}]
 */
Cypress.Commands.add('removeFixtureByName', (name, endpoint, options = {}) => {
    return cy.searchViaAdminApi({
        endpoint: endpoint,
        data: {
            field: options.identifier ? options.identifier : 'name',
            value: name
        }
    }).then((result) => {
        return cy.deleteViaAdminApi(endpoint, result.id)
    })
});

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createProductFixture
 * @function
 * @param {String} endpoint - API endpoint for the request
 */
Cypress.Commands.add('createProductFixture', () => {
    let json = {};
    let manufacturerId = '';
    let categoryId = '';

    return cy.fixture('product').then((result) => {
        json = result;

        return cy.createDefaultFixture('category');
    }).then((result) => {
        categoryId = result;

        return cy.searchViaAdminApi({
            endpoint: 'product-manufacturer',
            data: {
                field: 'name',
                value: 'shopware AG'
            }
        })
    }).then((result) => {
        manufacturerId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'tax',
            data: {
                field: 'name',
                value: '19%'
            }
        });
    }).then((result) => {
        return Object.assign({}, {
            taxId: result.id,
            manufacturerId: manufacturerId,
            categoryId: categoryId
        }, json);
    }).then((result) => {
        return cy.createViaAdminApi({
            endpoint: 'product',
            data: result
        });
    });
});

/**
 * Sets category and visibility for a product in order to set it visible in the Storefront
 * @memberOf Cypress.Chainable#
 * @name setProductFixtureVisibility
 * @function
 */
Cypress.Commands.add('setProductFixtureVisibility', () => {
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
                value: 'Product name'
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
                value: 'MainCategory'
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

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createCustomerFixture
 * @function
 */
Cypress.Commands.add('createCustomerFixture', () => {
    const addressId = uuid().replace(/-/g, '');
    const customerId = uuid().replace(/-/g, '');
    let customerJson = {};
    let customerAddressJson = {};
    let finalAddressRawData = {};
    let countryId = '';
    let groupId = '';
    let paymentId = '';
    let salesChannelId = '';
    let salutationId = '';

    return cy.fixture('customer').then((result) => {
        customerJson = result;

        return cy.fixture('customer-address')
    }).then((result) => {
        customerAddressJson = result;

        return cy.searchViaAdminApi({
            endpoint: 'country',
            data: {
                field: 'iso',
                value: 'DE'
            }
        });
    }).then((result) => {
        countryId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'payment-method',
            data: {
                field: 'name',
                value: 'Invoice'
            }
        });
    }).then((result) => {
        paymentId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'sales-channel',
            data: {
                field: 'name',
                value: 'Storefront'
            }
        });
    }).then((result) => {
        salesChannelId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'customer-group',
            data: {
                field: 'name',
                value: 'Standard customer group'
            }
        });
    }).then((result) => {
        groupId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'salutation',
            data: {
                field: 'displayName',
                value: 'Mr.'
            }
        });
    }).then((salutation) => {
        salutationId = salutation.id;

        finalAddressRawData = _.merge({
            addresses: [{
                customerId: customerId,
                salutationId: salutationId,
                id: addressId,
                countryId: countryId
            }]
        }, customerAddressJson);
    }).then(() => {
        return _.merge(customerJson, {
            salutationId: salutationId,
            defaultPaymentMethodId: paymentId,
            salesChannelId: salesChannelId,
            groupId: groupId,
            defaultBillingAddressId: addressId,
            defaultShippingAddressId: addressId
        });
    }).then((result) => {
        return _.merge(result, finalAddressRawData);
    }).then((result) => {
        return cy.createViaAdminApi({
            endpoint: 'customer',
            data: result
        });
    });
});
