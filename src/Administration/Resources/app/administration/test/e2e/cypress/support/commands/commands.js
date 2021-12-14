import '@percy/cypress';

/**
 * Takes a snapshot for percy visual testing
 * @memberOf Cypress.Chainable#
 * @name takeSnapshot
 * @param {String} title - Title of the screenshot
 * @param {String} [selectorToCheck = null] - Unique selector to make sure the module is ready for being snapshot
 * @param {Object} [width = null] - Screen width used for snapshot
 * @function
 */
Cypress.Commands.add('takeSnapshot', (title, selectorToCheck = null, width = null) => {
    if (!Cypress.env('usePercy')) {
        return;
    }

    if (selectorToCheck) {
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get(selectorToCheck).should('be.visible');
    }

    if (!width) {
        cy.percySnapshot(title);
        return;
    }
    cy.percySnapshot(title, width);
});

/**
 * Returns dynamic sales channel associations, such as the country, shipping method, payment method and a default category id
 * @memberOf Cypress.Chainable#
 * @name createDefaultSalesChannel
 * @function
 */
Cypress.Commands.add('createDefaultSalesChannel', (data = {}) => {
    return cy.searchViaAdminApi({
        endpoint: 'payment-method',
        data: {
            field: 'name',
            value: 'Invoice',
        },
    })
        .then((paymentMethod) => {
            data.paymentMethodId = paymentMethod.id;

            return cy.searchViaAdminApi({
                endpoint: 'shipping-method',
                data: {
                    field: 'name',
                    value: 'Standard',
                },
            });
        })
        .then((shippingMethod) => {
            data.shippingMethodId = shippingMethod.id;

            return cy.searchViaAdminApi({
                endpoint: 'category',
                data: {
                    field: 'name',
                    value: 'Home',
                },
            });
        })
        .then((category) => {
            data.navigationCategoryId = category.id;

            return cy.searchViaAdminApi({
                endpoint: 'country',
                data: {
                    field: 'name',
                    value: 'USA',
                },
            });
        })
        .then((country) => {
            data.countryId = country.id;

            return cy.createDefaultFixture('sales-channel', data);
        });
});
