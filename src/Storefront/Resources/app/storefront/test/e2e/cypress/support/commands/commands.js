const uuid = require('uuid/v4');
const RuleBuilderFixture = require('../service/fixture/rule-builder.fixture');
const ProductWishlistFixture = require('../service/fixture/product-wishlist.fixture');

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name setCustomerGroup
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {Object} [options={}] - Options concerning deletion
 */
Cypress.Commands.add('setCustomerGroup', (customerNumber, customerGroupData) => {
    let customer = '';

    return cy.fixture('customer-group').then((json) => {
        return cy.createViaAdminApi({
            endpoint: 'customer-group',
            data: customerGroupData
        });
    }).then(() => {
        return cy.searchViaAdminApi({
            endpoint: 'customer',
            data: {
                field: 'customerNumber',
                value: customerNumber
            }
        });
    }).then((result) => {
        customer = result;

        return cy.searchViaAdminApi({
            endpoint: 'customer-group',
            data: {
                field: 'name',
                value: customerGroupData.name
            }
        });
    }).then((result) => {
        return cy.updateViaAdminApi('customer', customer.id, {
            data: {
                groupId: result.id
            }
        })
    });
});

/**
 * Creates an entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name requestAdminApiStorefront
 * @function
 * @param {Object} data - Necessary  for the API request
 */
Cypress.Commands.add('requestAdminApiStorefront', (data) => {
    return cy.requestAdminApi(
        'POST',
        `api/${data.endpoint}?response=true`,
        data
    ).then((responseData) => {
        return responseData;
    });
});

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

        finalAddressRawData = Cypress._.merge({
            addresses: [{
                customerId: customerId,
                salutationId: salutationId,
                id: addressId,
                countryId: countryId
            }]
        }, customerAddressJson);
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

Cypress.Commands.add('typeAndCheckStorefront', {
    prevSubject: 'element'
}, (subject, value) => {
    cy.wrap(subject).type(value).invoke('val').should('eq', value);
});

Cypress.Commands.add('typeAndSelect', {
    prevSubject: 'element'
}, (subject, value) => {
    cy.wrap(subject).select(value);
});

Cypress.Commands.add('createRuleFixture', (userData, shippingMethodName = 'Standard') => {
    const fixture = new RuleBuilderFixture();

    return cy.fixture('rule-builder-shipping-payment.json').then((result) => {
        return Cypress._.merge(result, userData);
    }).then((data) => {
        return fixture.setRuleFixture(data, shippingMethodName);
    })
});

/**
 * Sets the analytics tracking to the desired state in Storefront sales channel
 * @memberOf Cypress.Chainable#
 * @name setAnalyticsFixtureToSalesChannel
 * @function
 * @param {Boolean} state - true: tracking is activated, false: tracking is deactivated
 */
Cypress.Commands.add('setAnalyticsFixtureToSalesChannel', (state) => {
    return cy.searchViaAdminApi({
        endpoint: 'sales-channel',
        data: {
            field: 'name',
            value: 'Storefront'
        }
    }).then((result) => {
        return cy.updateViaAdminApi('sales-channel', result.id, {
            data: {
                analytics: {
                    trackingId: 'UA-000000000-0',
                    active: state,
                    trackOrders: state,
                    anonymizeIp: state
                }
            }
        })
    });
});

/**
 * Set the product to the wishlist
 * @memberOf Cypress.Chainable#
 * @name setProductWishlist
 * @function
 */
Cypress.Commands.add('setProductWishlist', ({productId, customer}) => {
    const fixture = new ProductWishlistFixture();

    return fixture.setProductWishlist(productId, customer);
});

// WaitUntil command is from https://www.npmjs.com/package/cypress-wait-until
const logCommand = ({ options, originalOptions }) => {
    if (options.log) {
        options.logger({
            name: options.description,
            message: options.customMessage,
            consoleProps: () => originalOptions
        });
    }
};
const logCommandCheck = ({ result, options, originalOptions }) => {
    if (!options.log || !options.verbose) return;

    const message = [result];
    if (options.customCheckMessage) {
        message.unshift(options.customCheckMessage);
    }
    options.logger({
        name: options.description,
        message,
        consoleProps: () => originalOptions
    });
};

const waitUntil = (subject, checkFunction, originalOptions = {}) => {
    if (!(checkFunction instanceof Function)) {
        throw new Error("`checkFunction` parameter should be a function. Found: " + checkFunction);
    }

    const defaultOptions = {
        // base options
        interval: 200,
        timeout: 5000,
        errorMsg: "Timed out retrying",

        // log options
        description: "waitUntil",
        log: true,
        customMessage: undefined,
        logger: Cypress.log,
        verbose: false,
        customCheckMessage: undefined
    };
    const options = { ...defaultOptions, ...originalOptions };

    // filter out a falsy passed "customMessage" value
    options.customMessage = [options.customMessage, originalOptions].filter(Boolean);

    let retries = Math.floor(options.timeout / options.interval);

    logCommand({ options, originalOptions });

    const check = result => {
        logCommandCheck({ result, options, originalOptions });
        if (result) {
            return result;
        }
        if (retries < 1) {
            throw new Error(options.errorMsg);
        }
        cy.wait(options.interval, { log: false }).then(() => {
            retries--;
            return resolveValue();
        });
    };

    const resolveValue = () => {
        const result = checkFunction(subject);

        const isAPromise = Boolean(result && result.then);
        if (isAPromise) {
            return result.then(check);
        } else {
            return check(result);
        }
    };

    return resolveValue();
};

Cypress.Commands.add("waitUntil", { prevSubject: "optional" }, waitUntil);

/**
 * Cleans up any previous state by restoring database and clearing caches
 * @memberOf Cypress.Chainable#
 * @name cleanUpPreviousState
 * @function
 */
Cypress.Commands.overwrite('cleanUpPreviousState', (orig) => {
    if (Cypress.env('localUsage')) {
        return cy.exec(`${Cypress.env('shopwareRoot')}/bin/console e2e:restore-db`)
            .its('code').should('eq', 0);
    }

    return orig();
});
