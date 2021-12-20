import '@percy/cypress';

const { v4: uuid } = require('uuid');

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
 * Creates a variant product based on given fixtures "product-variants.json", 'tax,json" and "property.json"
 * with minor customisation
 * @memberOf Cypress.Chainable#
 * @name setVariantVisibility
 * @function
 * @param {String} [salesChannelName=Storefront] - The name of the sales channel for visibility
 */
Cypress.Commands.add('setVariantVisibility', (salesChannelName = 'Storefront', variantFixtures = 'product-variants-storefront') => {
    let product = null;

    return cy.fixture(variantFixtures).then((data) => {
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

        let first = true;
        finalAddressRawData = {
            addresses: customerAddressJson.addresses.map((a) => {
                let addrId;;
                if (first) {
                    addrId = addressId;
                    first = false;
                } else {
                    addrId = uuid().replace(/-/g, '');
                }
                cy.log(a.firstName)
                return Cypress._.merge({
                    customerId: customerId,
                    salutationId: salutationId,
                    id: addrId,
                    countryId: countryId
                }, a)
            })
        };
    }).then(() => {
        return Cypress._.merge(customerJson, {
            salutationId: salutationId,
            defaultPaymentMethodId: paymentId,
            salesChannelId: salesChannelId,
            groupId: groupId,
            defaultBillingAddressId: addressId,
            defaultShippingAddressId: addressId
        });
    }).then((result) => {
        return Cypress._.merge(result, finalAddressRawData);
    }).then((result) => {
        return cy.requestAdminApiStorefront({
            endpoint: 'customer',
            data: result
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
        method: 'post'
    }).as('saveData');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
        method: 'post'
    }).as('sales-channel');

    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
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
        method: 'POST'
    }).as('set-shipping');

    cy.contains(shippingMethod).should('be.visible').click();
    cy.get('.sw-settings-shipping-detail__condition_container').scrollIntoView();
    cy.get('.sw-settings-shipping-detail__condition_container .sw-entity-single-select__selection').should('be.visible')
        .type('Always valid (Default)');
    cy.get('.sw-select-result-list__content').contains('Always valid (Default)').should('be.visible').click();
    cy.get('.sw-settings-shipping-price-matrix').scrollIntoView();
    cy.get('.sw-data-grid__cell--price-EUR .sw-field--small:nth-of-type(1) [type]').clear().type(gross);
    cy.get('.sw-data-grid__cell--price-EUR .sw-field--small:nth-of-type(2) [type]').clear().type(net);
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
        method: 'POST'
    }).as('set-payment');

    cy.contains(paymentMethod).should('be.visible').click();
    cy.get('.sw-settings-payment-detail__condition_container').scrollIntoView();
    cy.get('.sw-settings-payment-detail__condition_container .sw-entity-single-select__selection').should('be.visible')
        .type('Always valid (Default)');
    cy.get('.sw-select-result-list__content').contains('Always valid (Default)').should('be.visible').click();
    cy.get('.sw-payment-detail__save-action').should('be.visible').click();
    cy.wait('@set-payment').its('response.statusCode').should('equal', 200);
});
/**
 * Navigates to sales channel detail page
 * @memberOf Cypress.Chainable#
 * @name goToSalesChannelDetail
 * @function
 * @param {String} salesChannel - Title of the sales channel
 */
Cypress.Commands.add('goToSalesChannelDetail', (salesChannel) => {
    cy.contains(salesChannel).should('be.visible').click();
    cy.contains('h2', salesChannel);
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
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/country`,
        method: 'post'
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
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/payment-method`,
        method: 'post'
    }).as('payment-method');

    cy.get('.sw-sales-channel-detail__select-payment-methods').scrollIntoView();
    cy.get('.sw-sales-channel-detail__select-payment-methods').then(($body) => {
        if (!$body.text().includes(paymentMethod)) {
            cy.get('.sw-sales-channel-detail__select-payment-methods .sw-select-selection-list__input').should('be.visible')
                .type(paymentMethod);
            cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').contains(paymentMethod).should('be.visible').click();
            cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-payment-methods').type(paymentMethod).should('be.visible');
    cy.wait('@payment-method').its('response.statusCode').should('equal', 200);
    cy.contains('.sw-select-result', paymentMethod).should('be.visible').click();
    cy.get('.sw-sales-channel-detail__save-action').should('be.visible').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
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
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/shipping-method`,
        method: 'post'
    }).as('shipping-method');

    cy.get('.sw-sales-channel-detail__select-shipping-methods').scrollIntoView();
    cy.get('.sw-sales-channel-detail__select-shipping-methods').then(($body) => {
        if (!$body.text().includes(shippingMethod)) {
            cy.get('.sw-sales-channel-detail__select-shipping-methods .sw-select-selection-list__input').should('be.visible')
                .type(shippingMethod);
            cy.wait('@shipping-method').its('response.statusCode').should('equal', 200);
            cy.get('.sw-select-result-list__content').contains(shippingMethod).should('be.visible').click();
            cy.wait('@shipping-method').its('response.statusCode').should('equal', 200);
        }
    });
    cy.get('.sw-sales-channel-detail__assign-shipping-methods').then(($body) => {
        if (!$body.text().includes(shippingMethod)) {
            cy.get('.sw-sales-channel-detail__assign-shipping-methods').type(shippingMethod).should('be.visible');
            cy.wait('@shipping-method').its('response.statusCode').should('equal', 200);
            cy.contains('.sw-select-result', shippingMethod).should('be.visible').click();
        }
    });
    cy.get('.sw-sales-channel-detail__save-action').should('be.visible').click();
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
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
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/currency`,
        method: 'post'
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
        method: 'post'
    }).as('sales-channel');
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/language`,
        method: 'post'
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
    cy.get('.sw-loader').should('not.exist');
    cy.wait('@sales-channel').its('response.statusCode').should('equal', 200);
    cy.get('.sw-sales-channel-detail__select-languages').scrollIntoView();
    cy.contains('.sw-sales-channel-detail__select-languages', language).should('be.visible');
    cy.contains('.sw-sales-channel-detail__assign-languages', language).should('be.visible');
});

/**
 * Logs in to the Administration manually
 * @memberOf Cypress.Chainable#
 * @name login
 * @function
 * @param {Object} userType - The type of the user logging in
 */
Cypress.Commands.add('login', (userType = 'admin') => {
    cy.intercept({
        url: `/api/_admin/snippets?locale=${Cypress.env('locale')}`,
        method: 'get'
    }).as('snippets').then(() => {
        const types = {
            admin: {
                name: 'admin',
                pass: 'shopware',
            },
        };

        const user = types[userType];

        cy.visit('/admin');

        cy.contains(/Username|Benutzername/);
        cy.contains(/Password|Passwort/);

        cy.get('#sw-field--username')
            .type(user.name)
            .should('have.value', user.name);
        cy.get('#sw-field--password')
            .type(user.pass)
            .should('have.value', user.pass);
        cy.get('.sw-login-login').submit();
        cy.contains('Dashboard');

        // the snippets are replaced after this has finished.
        // If we don't wait for this, it'll happen at a random point in time and might trigger a detached dom error.
        return cy.wait('@snippets');
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
