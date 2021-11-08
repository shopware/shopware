// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

/**
 * Creates a variant product based on given fixtures "product-variants.json", 'tax,json" and "property.json"
 * with minor customisation
 * @memberOf Cypress.Chainable#
 * @name createProductVariantFixture
 * @function
 * @param {String} [salesChannelName=Storefront] - The name of the sales channel for visibility
 */
Cypress.Commands.overwrite('createProductVariantFixture', () => {
    cy.log('##### Overwritten #######')

    return cy.createDefaultFixture('tax', {
        id: '91b5324352dc4ee58ec320df5dcf2bf4',
    }).then(() => {
        return cy.createPropertyFixture({
            options: [{
                id: '15532b3fd3ea4c1dbef6e9e9816e0715',
                name: 'Red',
            }, {
                id: '98432def39fc4624b33213a56b8c944f',
                name: 'Green',
            }],
        });
    }).then(() => {
        return cy.createPropertyFixture({
            name: 'Size',
            options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }],
        });
    }).then(() => {
        cy.createDefaultFixture('product', {}, 'product-variants.json');
    });
});

/**
 * Creates a variant product based on given fixtures "product-variants.json", 'tax,json" and "property.json"
 * with minor customisation
 * @memberOf Cypress.Chainable#
 * @name setVariantVisibility
 * @function
 * @param {String} [salesChannelName=Storefront] - The name of the sales channel for visibility
 */
Cypress.Commands.add('setVariantVisibility', (salesChannelName = 'Storefront') => {
    let product = null;

    return cy.fixture('product-variants').then((data) => {
        product = data;

        return cy.searchViaAdminApi({
            endpoint: 'sales-channel',
            data: {
                field: 'name',
                value: salesChannelName,
            },
        });
    }).then((salesChannel) => {
        return cy.updateViaAdminApi('product', product.id, {
            data: {
                visibilities: [{
                    visibility: 30,
                    salesChannelId: salesChannel.id,
                }],
            },
        });
    });
});
