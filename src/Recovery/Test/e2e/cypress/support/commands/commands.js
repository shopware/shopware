import '@percy/cypress';

/**
 * Logs in to the Administration manually
 * @memberOf Cypress.Chainable#
 * @name login
 * @function
 * @param {Object} userType - The type of the user logging in
 */
Cypress.Commands.add('login', (userType) => {
    const admin =  {
        name: 'admin',
        pass: 'shopware'
    };

    const user = userType ? types[userType] : admin;

    cy.get('#sw-field--username')
        .type(user.name)
        .should('have.value', user.name);
    cy.get('#sw-field--password')
        .type(user.pass)
        .should('have.value', user.pass);
    cy.get('.sw-login-login').submit();
    cy.contains('Dashboard');
});

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
        cy.get(selectorToCheck).should('be.visible');
    }

    if (!width) {
        cy.percySnapshot(`[${Cypress.env('testBase')}] ${title}`);
        return;
    }
    cy.percySnapshot(`[${Cypress.env('testBase')}] ${title}`, width);
});

/**
 * Sets the specific shipping method as default in sales channel
 * @memberOf Cypress.Chainable#
 * @name setShippingMethodInSalesChannel
 * @param {String} name - Name of the shipping method
 * @param {String} [salesChannel = Storefront]  - Name of the sales channel
 * @function
 */
Cypress.Commands.add('setShippingMethodInSalesChannel', (name, salesChannel = 'Storefront') => {
    let salesChannelId;

    // We need to assume that we're already logged in, so make sure to use loginViaApi command first
    return cy.searchViaAdminApi({
        endpoint: 'sales-channel',
        data: {
            field: 'name',
            value: salesChannel
        }
    }).then((data) => {
        salesChannelId = data.id;

        return cy.searchViaAdminApi({
            endpoint: 'shipping-method',
            data: {
                field: 'name',
                value: name
            }
        })
    }).then((data) => {
        return cy.updateViaAdminApi('sales-channel', salesChannelId, {
            data: {
                shippingMethodId: data.id
            }
        })
    });
});

/**
 * Updates an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name updateViaAdminApi2
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {String} id - Id of the entity to be updated
 * @param {Object} data - Necessary data for the API request
 */
Cypress.Commands.add('updateViaAdminApi', (endpoint, id, data) => {
    return cy.requestAdminApi('PATCH', `api/${endpoint}/${id}`, data).then((responseData) => {
        return responseData;
    });
});

/**
 * Updates an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name prepareAdminForScreenshot
 * @function
 */
Cypress.Commands.add('prepareAdminForScreenshot', () => {
    // Hide version information, as it could change
    cy.changeElementStyling(
        '.sw-version__info',
        'visibility: hidden'
    );

    if (Cypress.env('testBase') === 'Update') {
        cy.get('.sw-avatar')
            .should('have.css', 'background-image')
            .and('match', /Max%20Mustermann.png/);
    }
    cy.get('body').then(($body) => {
        if ($body.find('.sw-alert').length) {
            // Hide notifications for visual testing
            cy.changeElementStyling(
                '.sw-alert',
                'display: none'
            );
        }
    })
    cy.log('Admin successfully prepared for percy usage!')
});

/**
 * Updates an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name changeElementStyling
 * @function
 * @param {String} selector - API endpoint for the request
 * @param {String} imageStyle - API endpoint for the request
 */
Cypress.Commands.add('changeElementStyling', (selector, imageStyle) => {
    cy.get(selector)
        .invoke('attr', 'style', imageStyle)
        .should('have.attr', 'style', imageStyle);
});

/**
 * Sorts a listing via clicking on name column
 * @memberOf Cypress.Chainable#
 * @name sortListingViaColumn
 * @function
 * @param {String} columnTitle - Title of the column to sort with
 * @param {String} firstEntry - String of the first entry to be in listing after sorting
 * @param {String} [rowZeroSelector = .sw-data-grid__row--0]  - Name of the sales channel
 */
Cypress.Commands.add('sortListingViaColumn', (
    columnTitle,
    firstEntry,
    rowZeroSelector = '.sw-data-grid__row--0'
) => {
    cy.contains('.sw-data-grid__cell-content', columnTitle).should('be.visible');
    cy.contains('.sw-data-grid__cell-content', columnTitle).click();

    cy.get('.sw-data-grid__skeleton').should('not.exist');
    cy.get('.sw-data-grid__sort-indicator').should('be.visible');

    cy.get(rowZeroSelector).contains(firstEntry);
});

/**
 * Returns default sales channel for products
 * @memberOf Cypress.Chainable#
 * @name setSalesChannel
 * @function
 * @param {String} salesChannel - Title of the sales channel
 *
 */
Cypress.Commands.add('setSalesChannel', (salesChannel) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');
    cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
    cy.get('.sw-select-selection-list').click();
    cy.get('.sw-select-selection-list').then(($body) => {
        if ($body.text().includes(salesChannel)) {
            cy.get(".sw-settings-listing__save-action").click();
        } else {
            cy.get('.sw-select-selection-list__input').type(salesChannel);
            cy.contains('.sw-select-option--0.sw-select-result', salesChannel).click();
            cy.get('.sw-settings-listing__save-action').click();
        }
    })
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.contains('.sw-select-selection-list', salesChannel);
});

/**
 * Returns default settings for shipping method
 * @memberOf Cypress.Chainable#
 * @name setShippingMethod
 * @function
 * @param {String} shippingMethod - Title of the shipping method
 * @param {String} gross - Title of the gross price
 * @param {String} net - Title of the net price
 */
Cypress.Commands.add('setShippingMethod', (shippingMethod, gross, net) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/shipping-method`,
        method: 'POST'
    }).as('set-shipping');
    cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
    cy.contains(shippingMethod).click();
    cy.get('.sw-settings-shipping-detail__condition_container').scrollIntoView();
    cy.get('.sw-settings-shipping-detail__condition_container .sw-entity-single-select__selection').type('Always valid (Default)');
    cy.get('.sw-select-result-list__content').contains('Always valid (Default)').click();
    cy.get('.sw-settings-shipping-price-matrix').scrollIntoView();
    cy.get('.sw-data-grid__cell--price-EUR .sw-field--small:nth-of-type(1) [type]').clear().type(gross);
    cy.get('.sw-data-grid__cell--price-EUR .sw-field--small:nth-of-type(2) [type]').clear().type(net);
    cy.get('.sw-settings-shipping-method-detail__save-action').click();
    cy.wait('@set-shipping').its('response.statusCode').should('equal', 200);
});

/**
 * Returns default settings for payment method
 * @memberOf Cypress.Chainable#
 * @name setPaymentMethod
 * @function
 * @param {String} paymentMethod - Title of the payment method
 */
Cypress.Commands.add('setPaymentMethod', (paymentMethod) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/payment-method`,
        method: 'POST'
    }).as('set-payment');
    cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
    cy.contains(paymentMethod).click();
    cy.get('.sw-settings-payment-detail__condition_container').scrollIntoView();
    cy.get('.sw-settings-payment-detail__condition_container .sw-entity-single-select__selection').type('Always valid (Default)');
    cy.get('.sw-select-result-list__content').contains('Always valid (Default)').click();
    cy.get('.sw-payment-detail__save-action').click();
    cy.wait('@set-payment').its('response.statusCode').should('equal', 200);
});

/**
 * Returns country (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectCountry
 * @function
 * @param {String} salesChannel - Title of the sales channel
 * @param {String} country - Title of the country
 */
Cypress.Commands.add('selectCountry', (salesChannel, country) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/country`,
        method: 'post'
    }).as('country');
    cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
    cy.contains(salesChannel).click();
    cy.get('.sw-sales-channel-detail__select-countries').then(($body) => {
        if (!$body.text().includes(country)) {
            cy.get('.sw-sales-channel-detail__select-countries').find('.sw-select-selection-list__input').type(country);
            cy.get('.sw-select-result-list__content').contains(country).click();
            cy.wait('@country').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-countries').type(country);
    cy.wait('@country').its('response.statusCode').should('equal', 200)
    cy.get('.sw-select-result-list__content').contains(country).click();
    cy.get('.sw-sales-channel-detail__save-action').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-loader').should('not.exist');
    cy.contains('.sw-sales-channel-detail__select-countries', country);
    cy.contains('.sw-sales-channel-detail__assign-countries', country);
});

/**
 * Returns payment method (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectPayment
 * @function
 * @param {String} salesChannel - Title of the sales channel
 * @param {String} paymentMethod - Title of the payment method
 */
Cypress.Commands.add('selectPayment', (salesChannel, paymentMethod) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/payment-method`,
        method: 'post'
    }).as('payment-method');
    cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
    cy.contains(salesChannel).click();
        cy.get('.sw-sales-channel-detail__select-payment-methods').then(($body) => {
        if (!$body.text().includes(paymentMethod)) {
            cy.get('.sw-sales-channel-detail__select-payment-methods').find('.sw-select-selection-list__input').type(paymentMethod);
            cy.get('.sw-select-result-list__content').contains(paymentMethod).click();
            cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-payment-methods').type(paymentMethod);
    cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
    cy.get('.sw-select-result-list__content').contains(paymentMethod).click();
    cy.get('.sw-sales-channel-detail__save-action').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-loader').should('not.exist');
    cy.contains('.sw-sales-channel-detail__select-payment-methods', paymentMethod);
    cy.contains('.sw-sales-channel-detail__assign-payment-methods', paymentMethod);
});

/**
 * Returns shipping method (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectShipping
 * @function
 * @param {String} salesChannel - Title of the sales channel
 * @param {String} shippingMethod - Title of the shipping method
 */
Cypress.Commands.add('selectShipping', (salesChannel, shippingMethod) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/shipping-method`,
        method: 'post'
    }).as('shipping-method');
    cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
    cy.contains(salesChannel).click();
    cy.get('.sw-sales-channel-detail__select-shipping-methods').then(($body) => {
        if (!$body.text().includes(shippingMethod)) {
            cy.get('.sw-sales-channel-detail__select-shipping-methods').find('.sw-select-selection-list__input').type(shippingMethod);
            cy.get('.sw-select-result-list__content').contains(shippingMethod).click();
            cy.wait('@shipping-method').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-shipping-methods').type(shippingMethod);
    cy.wait('@shipping-method').its('response.statusCode').should('equal', 200);
    cy.get('.sw-select-result-list__content').contains(shippingMethod).click();
    cy.get('.sw-sales-channel-detail__save-action').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-loader').should('not.exist');
    cy.contains('.sw-sales-channel-detail__select-shipping-methods', shippingMethod);
    cy.contains('.sw-sales-channel-detail__assign-shipping-methods', shippingMethod);
});

/**
 * Returns currency (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectCurrency
 * @function
 * @param {String} salesChannel - Title of the sales channel
 * @param {String} currency - Title of the currency
 */
Cypress.Commands.add('selectCurrency', (salesChannel, currency) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/currency`,
        method: 'post'
    }).as('currency');
    cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
    cy.contains(salesChannel).click();
    cy.get('.sw-sales-channel-detail__select-currencies').then(($body) => {
        if (!$body.text().includes(currency)) {
            cy.get('.sw-sales-channel-detail__select-currencies').find('.sw-select-selection-list__input').type(currency);
            cy.wait('@currency').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').contains(currency).click();
            cy.wait('@currency').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-currencies').type(currency);
    cy.get('.sw-select-result-list__content').contains(currency).click();
    cy.get('.sw-sales-channel-detail__save-action').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-loader').should('not.exist');
    cy.contains('.sw-sales-channel-detail__select-currencies', currency);
    cy.contains('.sw-sales-channel-detail__assign-currencies', currency);
});

/**
 * Returns language (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectLanguage
 * @function
 * @param {String} salesChannel - Title of the sales channel
 * @param {String} language - Title of the language
 */
Cypress.Commands.add('selectLanguage', (salesChannel, language) => {
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `${Cypress.env('apiPath')}/search/language`,
        method: 'post'
    }).as('language');
    cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
    cy.contains(salesChannel).click();

    cy.get('.sw-sales-channel-detail__select-languages').then(($body) => {
        if (!$body.text().includes(language)) {
            cy.get('.sw-sales-channel-detail__select-languages').find('.sw-select-selection-list__input').type(language);
            cy.get('.sw-select-result-list__content').contains(language).click();
            cy.wait('@language').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-languages').type(language);
    cy.wait('@language').its('response.statusCode').should('equal', 200);
    cy.get('.sw-select-result-list__content').contains(language).click();
    cy.get('.sw-sales-channel-detail__save-action').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-loader').should('not.exist');
    cy.contains('.sw-sales-channel-detail__select-languages', language);
    cy.contains('.sw-sales-channel-detail__assign-languages', language);
});
