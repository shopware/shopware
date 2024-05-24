require('@percy/cypress');

const { v4: uuid } = require('uuid');

/**
 * Takes a snapshot for percy visual testing
 * @memberOf Cypress.Chainable#
 * @name takeSnapshot
 * @param {String} title - Title of the screenshot
 * @param {String} [selectorToCheck = null] - Unique selector to make sure the module is ready for being snapshot
 * @param {Object} [width = null] - Screen width used for snapshot
 * @param {Object} [percyCSS = null] - Add custom styling to snapshot
 * @function
 */
Cypress.Commands.add('takeSnapshot', (title, selectorToCheck = null, width = null, percyCSS = null) => {
    // if (!Cypress.env('usePercy')) {
    //     return;
    // }
    //
    // if (selectorToCheck) {
    //     cy.get('.sw-skeleton').should('not.exist');
    //     cy.get('.sw-loader').should('not.exist');
    //     cy.get('.sw-loader__element').should('not.exist');
    //     cy.get(selectorToCheck).should('be.visible');
    // }
    //
    // let options = {};
    // if (width) {
    //     Object.assign(options, width);
    // }
    //
    // if (percyCSS) {
    //     Object.assign(options, percyCSS);
    // }
    //
    // // Wait 1 second for the network to idle. This will reduce flackyness with missing icons etc.
    // cy.waitForNetworkIdle(1000);
    //
    // cy.percySnapshot(title, options);
});

/**
 * Creates a variant product based on given fixtures "product-variants.json", 'tax,json" and "property.json"
 * with minor customisation
 * @memberOf Cypress.Chainable#
 * @name createStorefrontProductVariantFixture
 * @function
 * @param {String} [salesChannelName=Storefront] - The name of the sales channel for visibility
 */
Cypress.Commands.add('createStorefrontProductVariantFixture', () => {
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
        cy.createDefaultFixture('product', {}, 'product-variants-storefront.json');
    });
});

/**
 * Create customer fixture using Shopware API at the given endpoint, tailored for Storefront
 * @memberOf Cypress.Chainable#
 * @name createCustomerFixtureStorefront
 * @function
 * @param {Object} userData - Options concerning creation
 */
Cypress.Commands.add('createCustomerFixtureStorefront', (userData) => {
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
        customerJson = Cypress._.merge(result, userData);

        return cy.fixture('customer-address');
    }).then((result) => {
        customerAddressJson = result;

        return cy.searchViaAdminApi({
            endpoint: 'country',
            data: {
                field: 'iso',
                value: 'DE',
            },
        });
    }).then((result) => {
        countryId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'payment-method',
            data: {
                field: 'name',
                value: 'Invoice',
            },
        });
    }).then((result) => {
        paymentId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'sales-channel',
            data: {
                field: 'name',
                value: 'Storefront',
            },
        });
    }).then((result) => {
        salesChannelId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'customer-group',
            data: {
                field: 'name',
                value: 'Standard customer group',
            },
        });
    }).then((result) => {
        groupId = result.id;

        return cy.searchViaAdminApi({
            endpoint: 'salutation',
            data: {
                field: 'displayName',
                value: 'Mr.',
            },
        });
    }).then((salutation) => {
        salutationId = salutation.id;

        let first = true;
        finalAddressRawData = {
            addresses: customerAddressJson.addresses.map((a) => {
                let addrId;
                if (first) {
                    addrId = addressId;
                    first = false;
                } else {
                    addrId = uuid().replace(/-/g, '');
                }
                cy.log(a.firstName);
                return Cypress._.merge({
                    customerId: customerId,
                    salutationId: salutationId,
                    id: addrId,
                    countryId: countryId,
                }, a);
            }),
        };
    }).then(() => {
        return Cypress._.merge(customerJson, {
            salutationId: salutationId,
            defaultPaymentMethodId: paymentId,
            salesChannelId: salesChannelId,
            groupId: groupId,
            defaultBillingAddressId: addressId,
            defaultShippingAddressId: addressId,
        });
    }).then((result) => {
        return Cypress._.merge(result, finalAddressRawData);
    }).then((result) => {
        return cy.requestAdminApiStorefront({
            endpoint: 'customer',
            data: result,
        });
    });
});

/**
 * Returns default sales channel for products
 * @memberOf Cypress.Chainable#
 * @name setSalesChannel
 * @function
 * @param {String} salesChannel - Title of the sales channel
 */
Cypress.Commands.add('setSalesChannel', (salesChannel) => {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
        method: 'POST',
    }).as('saveData');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'POST',
    }).as('sales-channel');

    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    // Wait for some time because .sw-select-selection-list needs some time to update
    cy.wait(500);
    cy.get('.sw-select-selection-list').then(($body) => {
        if ($body.text().includes(salesChannel)) {
            cy.get('.sw-settings-listing__save-action').click();
        } else {
            cy.get('.sw-select-selection-list__input').should('be.visible').type(salesChannel);
            cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
            cy.contains('.sw-select-option--0.sw-select-result', salesChannel).should('be.visible').click();
            cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
            cy.get('.sw-settings-listing__save-action').should('be.visible').click();
        }
    });
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-loader').should('not.exist');
    cy.wait('@saveData').its('response.statusCode').should('equal', 204);
    cy.contains('.sw-select-selection-list', salesChannel).should('be.visible');
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
        url: `**/${Cypress.env('apiPath')}/search/shipping-method`,
        method: 'POST',
    }).as('set-shipping');

    cy.contains(shippingMethod).should('be.visible').click();
    cy.get('.sw-settings-shipping-detail__condition_container').scrollIntoView();
    cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelectAndCheck(
        'Always valid (Default)',
        '.sw-settings-shipping-detail__top-rule',
    );
    cy.get('.sw-settings-shipping-price-matrix').scrollIntoView();
    cy.get('.sw-data-grid__cell--price-EUR .sw-field--small:nth-of-type(1) [type]').clearTypeAndCheck(gross);
    cy.get('.sw-data-grid__cell--price-EUR .sw-field--small:nth-of-type(2) [type]').clearTypeAndCheck(net);
    cy.get('.sw-settings-shipping-method-detail__save-action').should('be.visible').click();
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
        url: `**/${Cypress.env('apiPath')}/search/payment-method`,
        method: 'POST',
    }).as('set-payment');

    cy.get(".sw-payment-card").contains(paymentMethod).get("a").contains("Details bewerken").click();
    cy.get('.sw-settings-payment-detail__condition_container').scrollIntoView();
    cy.get('.sw-settings-payment-detail__field-availability-rule').typeSingleSelectAndCheck(
        'Always valid (Default)',
        '.sw-settings-payment-detail__field-availability-rule',
    );
    cy.get('.sw-payment-detail__save-action').should('be.visible').click();
    cy.wait('@set-payment').its('response.statusCode').should('equal', 200);
});

/**
 * Returns country (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectCountryForSalesChannel
 * @function
 * @param {String} country - Title of the country
 */
Cypress.Commands.add('selectCountryForSalesChannel', (country) => {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'POST',
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/country`,
        method: 'POST',
    }).as('country');

    cy.get('.sw-sales-channel-detail__select-countries').then(($body) => {
        if (!$body.text().includes(country)) {
            cy.get('.sw-sales-channel-detail__select-countries .sw-select-selection-list__input').should('be.visible').type(country);
            cy.wait('@country').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.get('.sw-select-result-list__content').contains(country).should('be.visible').click({ force:true });
            cy.wait('@country').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-countries').then(($body) => {
        if (!$body.text().includes(country)) {
            cy.get('.sw-sales-channel-detail__assign-countries').should('be.visible').type(country);
            cy.wait('@country').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result').should('have.length', 1);
            cy.contains('.sw-select-result', country).should('be.visible').click({ force:true });
        }
    });
    cy.get('.sw-sales-channel-detail__save-action').should('be.visible').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');
    cy.contains('.sw-sales-channel-detail__select-countries', country).should('be.visible');
    cy.contains('.sw-sales-channel-detail__assign-countries', country).should('be.visible');
});

/**
 * Returns payment method (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectPaymentMethodForSalesChannel
 * @function
 * @param {String} paymentMethod - Title of the payment method
 */
Cypress.Commands.add('selectPaymentMethodForSalesChannel', (paymentMethod) => {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'POST',
    }).as('sales-channel');
    cy.intercept('POST', `**/${Cypress.env('apiPath')}/search/payment-method`, (req) => {
        const { body } = req;
        if (body.hasOwnProperty('term') && body.term === paymentMethod) {
            req.alias = 'payment-method-search-for';
        } else {
            req.alias = 'payment-method';
        }
    });
    cy.get('.sw-sales-channel-detail__select-payment-methods').scrollIntoView();
    cy.get('.sw-sales-channel-detail__select-payment-methods').then(($body) => {
        if (!$body.text().includes(paymentMethod)) {
            cy.get('.sw-sales-channel-detail__select-payment-methods .sw-select-selection-list__input').should('be.visible')
                .type(paymentMethod);
            cy.wait('@payment-method-search-for').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.get('.sw-select-result-list__content').contains(paymentMethod).should('be.visible').click({ force:true });
            cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-payment-methods').type(paymentMethod).should('be.visible');
    cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
    cy.get('.sw-select-result').should('have.length', 1);
    cy.contains('.sw-select-result', paymentMethod).should('be.visible').click({ force:true });
    cy.get('.sw-sales-channel-detail__save-action').should('be.visible').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');
    cy.get('.sw-sales-channel-detail__select-payment-methods').scrollIntoView();
    cy.contains('.sw-sales-channel-detail__select-payment-methods', paymentMethod).should('be.visible');
    cy.contains('.sw-sales-channel-detail__assign-payment-methods', paymentMethod).should('be.visible');
});

/**
 * Returns shipping method (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectShippingMethodForSalesChannel
 * @function
 * @param {String} shippingMethod - Title of the shipping method
 */
Cypress.Commands.add('selectShippingMethodForSalesChannel', (shippingMethod) => {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'POST',
    }).as('sales-channel');
    cy.intercept('POST', `**/${Cypress.env('apiPath')}/search/shipping-method`, (req) => {
        const { body } = req;
        if (body.hasOwnProperty('term') && body.term === shippingMethod) {
            req.alias = 'shipping-method-search-for';
        } else {
            req.alias = 'shipping-method';
        }
    });
    cy.get('.sw-sales-channel-detail__select-shipping-methods').scrollIntoView();
    cy.get('.sw-sales-channel-detail__select-shipping-methods').then(($body) => {
        if (!$body.text().includes(shippingMethod)) {
            cy.get('.sw-sales-channel-detail__select-shipping-methods .sw-select-selection-list__input').should('be.visible')
                .type(shippingMethod);
            cy.wait('@shipping-method-search-for').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.get('.sw-select-result-list__content').contains(shippingMethod).should('be.visible').click({ force:true });
            cy.wait('@shipping-method').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-shipping-methods').then(($body) => {
        if (!$body.text().includes(shippingMethod)) {
            cy.get('.sw-sales-channel-detail__assign-shipping-methods').type(shippingMethod).should('be.visible');
            cy.wait('@shipping-method-search-for').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result').should('have.length', 1);
            cy.contains('.sw-select-result', shippingMethod).should('be.visible').click({ force:true });
        }
    });
    cy.get('.sw-sales-channel-detail__save-action').should('be.visible').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');
    cy.get('.sw-sales-channel-detail__select-shipping-methods').scrollIntoView();
    cy.contains('.sw-sales-channel-detail__select-shipping-methods', shippingMethod).should('be.visible');
    cy.contains('.sw-sales-channel-detail__assign-shipping-methods', shippingMethod).should('be.visible');
});

/**
 * Returns currency (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectCurrencyForSalesChannel
 * @function
 * @param {String} currency - Title of the currency
 */
Cypress.Commands.add('selectCurrencyForSalesChannel', (currency) => {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'POST',
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/currency`,
        method: 'POST',
    }).as('currency');

    cy.get('.sw-sales-channel-detail__select-currencies').scrollIntoView();
    cy.get('.sw-sales-channel-detail__select-currencies').then(($body) => {
        if (!$body.text().includes(currency)) {
            cy.get('.sw-sales-channel-detail__select-currencies .sw-select-selection-list__input').type(currency).should('be.visible');
            cy.wait('@currency').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.get('.sw-select-result-list__content').contains(currency).should('be.visible').click({ force:true });
            cy.wait('@currency').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-currencies').then(($body) => {
        if (!$body.text().includes(currency)) {
            cy.get('.sw-sales-channel-detail__assign-currencies').type(currency).should('be.visible');
            cy.wait('@currency').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.contains('.sw-select-result', currency).click({ force:true });
        }
    });
    cy.get('.sw-sales-channel-detail__save-action').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');
    cy.get('.sw-sales-channel-detail__select-currencies').scrollIntoView();
    cy.contains('.sw-sales-channel-detail__select-currencies', currency).should('be.visible');
    cy.contains('.sw-sales-channel-detail__assign-currencies', currency).should('be.visible');
});


/**
 * Returns language (selects and assign as default) for sales channel
 * @memberOf Cypress.Chainable#
 * @name selectLanguageForSalesChannel
 * @function
 * @param {String} language - Title of the language
 */
Cypress.Commands.add('selectLanguageForSalesChannel', (language) => {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'POST',
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/language`,
        method: 'POST',
    }).as('language');

    cy.get('.sw-sales-channel-detail__select-languages').scrollIntoView();
    cy.get('.sw-sales-channel-detail__select-languages').then(($body) => {
        if (!$body.text().includes(language)) {
            cy.get('.sw-sales-channel-detail__select-languages .sw-select-selection-list__input').type(language).should('be.visible');
            cy.wait('@language').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.get('.sw-select-result-list__content').contains(language).should('be.visible').click({ force:true });
            cy.wait('@language').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-languages').then(($body) => {
        if (!$body.text().includes(language)) {
            cy.get('.sw-sales-channel-detail__assign-languages').type(language).should('be.visible');
            cy.wait('@language').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').should('have.length', 1);
            cy.contains('.sw-select-result', language).should('be.visible').click({ force:true });
        }
    });
    cy.get('.sw-sales-channel-detail__save-action').should('be.visible').click();
    cy.get('.sw-skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-sales-channel-detail__select-languages').scrollIntoView();
    cy.contains('.sw-sales-channel-detail__select-languages', language).should('be.visible');
    cy.contains('.sw-sales-channel-detail__assign-languages', language).should('be.visible');
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
        'visibility: hidden',
    );

    if (Cypress.env('testBase') === 'Update') {
        cy.get('.sw-avatar')
            .should('have.css', 'background-image')
            .and('match', /Max%20Mustermann.png/);
    }
    cy.get('body').then(($body) => {
        if ($body.find('.sw-notification__alert').length) {
            // Hide notifications for visual testing
            cy.changeElementStyling(
                '.sw-notification__alert',
                'display: none',
            );
        }
    });
    cy.log('Admin successfully prepared for percy usage!');
});

/**
 * Remove the prepared admin changes for screenshot
 * @memberOf Cypress.Chainable#
 * @name resetAdminChangesForScreenshot
 * @function
 */
Cypress.Commands.add('resetAdminChangesForScreenshot', () => {
    cy.changeElementStyling(
        '.sw-version__info',
        'visibility: visible',
    );

    // Find all open ".sw-alert--notification" and close them
    cy.get('.sw-alert--notification').then(($alerts) => {
        if ($alerts.length) {
            cy.changeElementStyling(
                '.sw-notification__alert',
                'display: block',
            );

            cy.get('.sw-alert--notification .sw-alert__close').click({ multiple: true });
        }
    });
});

/**
 * Creates a product with multiple reviews
 * @memberOf Cypress.Chainable#
 * @name createMultipleReviewsFixture
 * @param {array} additionalReviews - Array with reviews which will be created additionally
 * @param {Boolean} overwriteReviews - Set to true to only use the reviews passed by `additionalReviews`
 * @function
 */
Cypress.Commands.add('createMultipleReviewsFixture', (additionalReviews = [], overwriteReviews= false) => {
    // Use a fixed `productId` to assign reviews to product
    const productId = '83450210115646e7acd1ac896452a5f3';
    let product = null;
    let salesChannelId = null;

    const fixtureReviews = [
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 5,
            title: "Best product ever",
        },
        {
            content: "Lorem ipsum Exercitationem qui placeat labore similique.",
            points: 2,
            title: "Meh.",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 3,
            title: "It could be worse.",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 1,
            title: "Not the yellow from the egg.",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 5,
            title: "My life has changed!",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 4,
            title: "Pretty good overall",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 5,
            title: "Best ever",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 5,
            title: "This is not a bought review at all. 5 stars!!!",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 5,
            title: "This is really nice",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 1,
            title: "I want my money back",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 3,
            title: "Average...",
        },
        {
            content: "Exercitationem qui placeat labore similique.",
            points: 5,
            title: "Profit!",
        },
        ...additionalReviews,
    ];

    const productReviews = overwriteReviews ? additionalReviews : fixtureReviews;

    return cy.createProductFixture({ id: productId }).then(() => {
        return cy.fixture('product');
    }).then((result) => {
        product = result;
    }).then(() => {
        // Sales channel id is needed in order to display the reviews
        return cy.searchViaAdminApi({
            endpoint: 'sales-channel',
            data: {
                field: 'name',
                value: 'Storefront',
            },
        });
    }).then((response) => {
        salesChannelId = response.id;

        return cy.authenticate();
    }).then((result) => {
        // Create reviews with sync API
        return cy.request({
            headers: {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${result.access}`,
                'Content-Type': 'application/json',
            },
            method: 'POST',
            url: '/api/_action/sync',
            qs: {
                response: true,
            },
            body: {
                'write-product_review': {
                    entity: 'product_review',
                    action: 'upsert',
                    payload: productReviews.map((review) => {
                        return {
                            ...review,
                            ...{
                                productId: productId,
                                salesChannelId: salesChannelId,
                                status: true,
                            },
                        };
                    }),
                },
            },
        });
    }).then((reviews) => {
        // Return created product and reviews for further processing
        return { product, reviews };
    });
});

/**
 * Changes text of an element. Useful for visual testing. Be aware you'll influence the test using this.
 * @memberOf Cypress.Chainable#
 * @name changeElementText
 * @function
 * @param {String} selector - API endpoint for the request
 * @param {String} text - API endpoint for the request
 */
Cypress.Commands.add('changeElementText', (selector, text) => {
    cy.get(selector)
        .invoke('text', text)
        .should('contain', text);
});

/**
 * checks iframe content for sdk test
 * @memberOf Cypress.Chainable#
 * @name getSDKiFrame
 * @param {strong} iframe - String of custom url to select iframe
 * @function
 */
Cypress.Commands.add('getSDKiFrame', (locationId) => {
    cy.get(`iframe[src*="location-id=${locationId}"]`)
        .its('0.contentDocument.body')
        .should('not.be.empty')
        .then(cy.wrap);
});


Cypress.Commands.overwrite(
    'clickMainMenuItem',
    (orig, { targetPath, mainMenuId, subMenuId = null }) => {
        const finalMenuItem = `.sw-admin-menu__item--${mainMenuId}`;

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-admin-menu')
            .should('be.visible')
            .then(() => {
                if (subMenuId) {
                    cy.get(finalMenuItem).click();
                    cy.get(`.sw-admin-menu__item--${mainMenuId} .router-link-active`).should('be.visible');
                    cy.get(`.sw-admin-menu__navigation-list-item .${subMenuId}`).should('be.visible')
                        .then($el => Cypress.dom.isDetached($el));
                    cy.log(`Element ${subMenuId} is detached.`);
                    cy.get(`.sw-admin-menu__navigation-list-item .${subMenuId}`).should('be.visible')
                        .then($el => Cypress.dom.isAttached($el));
                    cy.log(`Element ${subMenuId} is now attached to the DOM.`);

                    cy.get(`.sw-admin-menu__item--${mainMenuId} .sw-admin-menu__navigation-list-item.${subMenuId}`)
                        .should('be.visible');

                    cy.get(`.sw-admin-menu__item--${mainMenuId} .sw-admin-menu__navigation-list-item.${subMenuId}`)
                        .click();
                } else {
                    cy.get(finalMenuItem).should('be.visible').click();
                }
            });
        cy.url().should('include', targetPath);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    },
);


// create session caching id based on credentials and scope
Cypress.Commands.add('authenticate', ({ username, password, scopes, id } = {} ) => {
    const user = username || Cypress.env('username') || Cypress.env('user') || 'admin';
    cy.session(
        ['bearerAuth', user, password, scopes, id],
        () => {
            cy.request(
                'POST',
                '/api/oauth/token',
                {
                    grant_type: Cypress.env('grant') ? Cypress.env('grant') : 'password',
                    client_id: Cypress.env('client_id') ? Cypress.env('client_id') : 'administration',
                    scope: scopes || (Cypress.env('scope') ? Cypress.env('scope') : 'write'),
                    username: user,
                    password: password || Cypress.env('password') || Cypress.env('pass') || 'shopware',
                },
            ).then((responseData) => {
                let result = responseData.body;
                result.access = result.access_token;
                result.refresh = result.refresh_token;
                result.expiry = Math.round(Date.now() + (result.expires_in * 1000));

                cy.log('request /api/oauth/token');
                cy.log('cookieValue:', result);

                cy.setCookie(
                    'lastActivity',
                    `${Math.round(+new Date() / 1000)}`,
                    {
                        path: Cypress.env('admin'),
                        sameSite: "strict",
                    },
                );

                return cy.setCookie(
                    'bearerAuth',
                    JSON.stringify(result),
                    {
                        path: Cypress.env('admin'),
                        sameSite: "strict",
                    },
                );
            });
        },
        {
            validate: () => {
                return cy.getCookie('bearerAuth').then((cookie) => {
                    const cookieValue = JSON.parse(decodeURIComponent(cookie && cookie.value));

                    cy.log('cookieValue:', decodeURIComponent(cookie && cookie.value));

                    return cy.request({
                        method: 'GET',
                        url: '/api/_info/version',
                        failOnStatusCode: true,
                        headers: {
                            Authorization: `Bearer ${cookieValue && cookieValue.access}`,
                        },
                    }).then(() => true);
                });
            },
        },
    );

    return cy.getBearerAuth();
});


// changed to use authenticate with the cypress session feature
Cypress.Commands.add('loginAsUserWithPermissions', {
    prevSubject: false,
}, (permissions, username = 'maxmuster', password = 'Passw0rd!') => {
    cy.log('Login as user with permissions');

    const id = uuid().replace(/-/g, '');

    return cy.url().then((currentUrl) => {
        return cy.authenticate({ scopes: 'write user-verified' }).then((result) => {
            cy.openInitialPage('/admin');

            return cy.window().then(($w) => {
                const roleID = 'ef68f039468d4788a9ee87db9b3b94de';
                const localeId = $w.Shopware.State.get('session').currentUser.localeId;
                let headers = {
                    Accept: 'application/vnd.api+json',
                    Authorization: `Bearer ${result.access}`,
                    'Content-Type': 'application/json',
                };

                cy.request({
                    url: '/api/acl-role',
                    method: 'POST',
                    headers: headers,
                    body: {
                        id: roleID,
                        name: 'e2eRole',
                        privileges: (() => {
                            const privilegesService = $w.Shopware.Service('privileges');

                            const adminPrivileges = permissions.map(({ key, role }) => `${key}.${role}`);
                            return privilegesService.getPrivilegesForAdminPrivilegeKeys(adminPrivileges);
                        })(),
                    },
                });

                // save user
                cy.request({
                    url: '/api/user',
                    method: 'POST',
                    headers: headers,
                    body: {
                        aclRoles: [{ id: roleID }],
                        admin: false,
                        email: 'max@muster.com',
                        firstName: 'Max',
                        id: id,
                        lastName: 'Muster',
                        localeId: localeId,
                        password,
                        username,
                    },
                });
                // We need to wait so that last_updated_password_at isn't the same as the token issue timestamp.
                // Otherwise, the token is considered to be revoked for security reasons, because the password was changed
                // after the token was issued.
                cy.wait(2000);

                return cy.authenticate({ username, password, id }).then(() => {
                    return cy.openInitialPage(currentUrl);
                });
            });
        });
    });
});

// removed the wait on ma and just wait for the skeleton to disappear
Cypress.Commands.add('openInitialPage', (url) => {
    cy.log('All preparation done!');

    cy.visit(url);
    cy.get('.sw-desktop').should('be.visible');

    cy.get('.sw-skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');
});
