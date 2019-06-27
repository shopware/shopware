const sample = require('lodash.sample');

/**
 * Get the sales channel Id via Admin API
 * @memberOf Cypress.Chainable#
 * @name getSalesChannelId
 * @function
 */
Cypress.Commands.add('getSalesChannelId', () => {
    return cy.authenticate().then((result) => {
        const parameters = {
            data: {
                headers: {
                    Accept: 'application/vnd.api+json',
                    Authorization: `Bearer ${result.access}`,
                    'Content-Type': 'application/json'
                },
                field: 'name',
                value: Cypress.env('salesChannelName')
            },
            endpoint: 'sales-channel'
        };

        return cy.searchViaAdminApi(parameters).then((data) => {
            return data.attributes.accessKey;
        });
    });
});

/**
 * Do Storefront Api Requests
 * @memberOf Cypress.Chainable#
 * @name storefrontApiRequest
 * @function
 * @param {string} HTTP-Method
 * @param {string} endpoint name
 * @param {Object} header
 * @param {Object} body
 */
Cypress.Commands.add('storefrontApiRequest', (method, endpoint, header = {}, body = {}) => {
    return cy.getSalesChannelId().then((salesChannelAccessKey) => {
        const requestConfig = {
            headers: {
                'SW-Access-Key': salesChannelAccessKey,
                ...header
            },
            body: {
                ...body
            },
            method: method,
            url: `/sales-channel-api/v1/${endpoint}`
        };

        return cy.request(requestConfig).then((result) => {
            return result.body.data;
        });
    });
});

/**
 * Returns random product with id, name and url to view product
 * @memberOf Cypress.Chainable#
 * @name getRandomProductInformationForCheckout
 * @function
 */
Cypress.Commands.add('getRandomProductInformationForCheckout', () => {
    return cy.storefrontApiRequest('GET', 'product').then((result) => {
        const randomProduct = sample(result);

        return {
            id: randomProduct.id,
            name: randomProduct.name,
            net: randomProduct.price.net,
            gross: randomProduct.price.gross,
            listingPrice: randomProduct.calculatedListingPrice.unitPrice,
            url: `/detail/${randomProduct.id}`
        };
    });
});
