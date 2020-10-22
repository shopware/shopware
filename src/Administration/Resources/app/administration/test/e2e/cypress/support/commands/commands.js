import '@percy/cypress';

/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name typeAndCheckSearchField
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheckSearchField', {
    prevSubject: 'element'
}, (subject, value) => {
    // Request we want to wait for later
    cy.server();
    cy.route({
        url: `${Cypress.env('apiPath')}/search/**`,
        method: 'post'
    }).as('searchResultCall');

    cy.wrap(subject).type(value).should('have.value', value);

    cy.wait('@searchResultCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);

        cy.url().should('include', encodeURI(value));
    });
});

/**
 * Add role with Permissions
 * @memberOf Cypress.Chainable#
 * @name loginAsUserWithPermissions
 * @function
 * @param {Array} permissions - The permissions for the role
 */
Cypress.Commands.add('loginAsUserWithPermissions', {
    prevSubject: false
}, (permissions) => {
    cy.window().then(($w) => {
        const roleID = 'ef68f039468d4788a9ee87db9b3b94de';
        const localeId = $w.Shopware.State.get('session').currentUser.localeId;
        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${$w.Shopware.Context.api.authToken.access}`,
            'Content-Type': 'application/json'
        };

        cy.request({
            url: '/api/oauth/token',
            method: 'POST',
            headers: headers,
            body: {
                grant_type: 'password',
                client_id: 'administration',
                scope: 'user-verified',
                username: 'admin',
                password: 'shopware'
            }
        }).then(response => {
            // overwrite headers with new scope
            headers = {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${response.body.access_token}`,
                'Content-Type': 'application/json'
            };

            return cy.request({
                url: `/api/${Cypress.env('apiVersion')}/acl-role`,
                method: 'POST',
                headers: headers,
                body: {
                    id: roleID,
                    name: 'e2eRole',
                    privileges: (() => {
                        const privilegesService = $w.Shopware.Service('privileges');

                        const adminPrivileges = permissions.map(({ key, role }) => `${key}.${role}`);
                        return privilegesService.getPrivilegesForAdminPrivilegeKeys(adminPrivileges);
                    })()
                }
            });
        }).then(response => {
            // save user
            cy.request({
                url: `/api/${Cypress.env('apiVersion')}/user`,
                method: 'POST',
                headers: headers,
                body: {
                    aclRoles: [{ id: roleID }],
                    admin: false,
                    email: 'max@muster.com',
                    firstName: 'Max',
                    id: 'b7fb49e9d86d4e5b9b03c9d6f929e36b',
                    lastName: 'Muster',
                    localeId: localeId,
                    password: 'Passw0rd!',
                    username: 'maxmuster'
                }
            });
        });

        // logout
        cy.get('.sw-admin-menu__user-actions-toggle').click();
        cy.clearCookies();
        cy.get('.sw-admin-menu__logout-action').click();
        cy.get('.sw-login__container').should('be.visible');
        cy.reload().then(() => {
            cy.get('.sw-login__container').should('be.visible');

            // login
            cy.get('#sw-field--username').type('maxmuster');
            cy.get('#sw-field--password').type('Passw0rd!');
            cy.get('.sw-login__login-action').click();
            cy.contains('Max Muster');
        });
    });
});

/**
 * Cleans up any previous state by restoring database and clearing caches
 * @memberOf Cypress.Chainable#
 * @name openInitialPage
 * @function
 */
Cypress.Commands.add('openInitialPage', (url) => {
    // Request we want to wait for later
    cy.server();
    cy.route(`${Cypress.env('apiPath')}/_info/me`).as('meCall');

    cy.log('All preparation done!');
    cy.visit(url);
    cy.wait('@meCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);
    });
    cy.get('.sw-desktop').should('be.visible');
});

/**
 * Logs in silently using Shopware API
 * @memberOf Cypress.Chainable#
 * @name loginViaApi
 * @function
 */
Cypress.Commands.add('loginViaApi', () => {
    return cy.authenticate().then((result) => {
        return cy.window().then((win) => {
            cy.setCookie('bearerAuth', JSON.stringify(result));

            // Return bearer token
            return cy.getCookie('bearerAuth');
        }).then((win) => {
            cy.log('Now, fixtures are created - if necessary...');
        });
    });
});

/**
 * Logs in silently using Shopware API
 * @memberOf Cypress.Chainable#
 * @name createReviewFixture
 * @function
 */
Cypress.Commands.add('createReviewFixture', () => {
    // TODO move into e2e-testsuite-platform and use own service completely

    let reviewJson = null;
    let productId = '';
    let customerId = '';
    let salesChannelId = '';

    return cy.fixture('product-review').then((data) => {
        reviewJson = data;

        return cy.getCookie('bearerAuth');
    }).then((result) => {
        const headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${JSON.parse(result.value).access}`,
            'Content-Type': 'application/json'
        };

        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        }).then((data) => {
            customerId = data.id;

            return cy.searchViaAdminApi({
                endpoint: 'product',
                data: {
                    field: 'name',
                    value: 'Product name'
                }
            });
        }).then((data) => {
            productId = data.id;

            return cy.searchViaAdminApi({
                endpoint: 'sales-channel',
                data: {
                    field: 'name',
                    value: 'Storefront'
                }
            });
        })
            .then((data) => {
                salesChannelId = data.id;

                return cy.searchViaAdminApi({
                    endpoint: 'language',
                    data: {
                        field: 'name',
                        value: 'English'
                    }
                });
            })
            .then((data) => {
                cy.request({
                    url: `/api/${Cypress.env('apiVersion')}/product-review`,
                    method: 'POST',
                    headers: headers,
                    body: Cypress._.merge(reviewJson, {
                        customerId: customerId,
                        languageId: data.id,
                        productId: productId,
                        salesChannelId: salesChannelId
                    })
                });
            });
    });
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
        cy.percySnapshot(title);
        return;
    }
    cy.percySnapshot(title, width);
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
        });
    }).then((data) => {
        return cy.updateViaAdminApi('sales-channel', salesChannelId, {
            data: {
                shippingMethodId: data.id
            }
        });
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
    return cy.requestAdminApi('PATCH', `api/v2/${endpoint}/${id}`, data).then((responseData) => {
        return responseData;
    });
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
 * Types in a sw-select field and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeMultiSelectAndCheck
 * @function
 * @param {String} value - Desired value of the element
 * @param {Object} [options={}] - Options concerning swSelect usage
 */
Cypress.Commands.add('typeMultiSelectAndCheck', {
    prevSubject: 'element'
}, (subject, value, options = {}) => {
    const resultPrefix = '.sw-select';
    const inputCssSelector = '.sw-select-selection-list__input';
    const searchTerm = options.searchTerm || value;
    const position = options.position || 0;

    // Request we want to wait for later
    cy.server();
    cy.route({
        url: `${Cypress.env('apiPath')}/search/*`,
        method: 'post'
    }).as('filteredResultCall');

    cy.wrap(subject).should('be.visible');

    // type in the search term if available
    if (options.searchTerm) {
        cy.get(`${subject.selector} ${inputCssSelector}`).type(searchTerm);
        cy.get(`${subject.selector} ${inputCssSelector}`).should('have.value', searchTerm);

        if(options.clock) {
            cy.clock().then((clock) => {
                clock.tick(1000);
            });
        }
        cy.wait('@filteredResultCall').then(() => {
            cy.get(`${resultPrefix}-option--${position}`).should('be.visible');

            cy.wait('@filteredResultCall').then(() => {
                cy.get('.sw-loader__element').should('not.exist');
            });
        });

        cy.get(`${resultPrefix}-option--${position}`).should('be.visible');
        cy.get(`${resultPrefix}-option--${position} .sw-highlight-text__highlight`).contains(value);

        // select the first result (or at another position)
        cy.get(`${resultPrefix}-option--${position}`)
            .click({force: true});
    } else {
        cy.wrap(subject).click();

        if(options.clock) {
            cy.clock().then((clock) => {
                clock.tick(1000);
            });
        }
        cy.get('.sw-select-result').should('be.visible');
        cy.contains('.sw-select-result', value).click();
    }

    // in multi selects we can check if the value is the selected item
    cy.get(`${subject.selector} .sw-select-selection-list__item-holder--0`).contains(value);

    // close search results
    cy.get(`${subject.selector} ${inputCssSelector}`).type('{esc}');
    cy.get(`${subject.selector} .sw-select-result-list`).should('not.exist');
});

/**
 * Types in an sw-select field
 * @memberOf Cypress.Chainable#
 * @name typeSingleSelect
 * @function
 * @param {String} value - Desired value of the element
 * @param {String} selector - selector of the element
 * @param {Object} [options={}] - Options concerning swSelect usage
 */
Cypress.Commands.add('typeSingleSelect', {
    prevSubject: 'element'
}, (subject, value, selector, options = {}) => {
    const resultPrefix = '.sw-select';
    const inputCssSelector = `.sw-select__selection input`;

    cy.wrap(subject).should('be.visible');
    cy.wrap(subject).click();

    // type in the search term if available
    if (value) {
        cy.get('.sw-select-result-list').should('be.visible');
        cy.get(`${selector} ${inputCssSelector}`).clear();
        cy.get(`${selector} ${inputCssSelector}`).type(value);
        cy.get(`${selector} ${inputCssSelector}`).should('have.value', value);

        // Wait the debounce time for the search to begin
        if(options.clock) {
            cy.clock().then((clock) => {
                clock.tick(1000);
            });
        }

        cy.get(`${selector}.sw-loader__element`).should('not.exist');

        cy.get(`${selector} .is--disabled`)
            .should('not.exist');

        cy.get('.sw-select-result__result-item-text')
            .should('be.visible');

        cy.get('.sw-select-result__result-item-text')
            .contains(value).click({force: true});
    } else {
        // Select the first element
        cy.get(`${resultPrefix}-option--0`).click({force: true});
    }
});


/**
 * Types in an sw-select field and checks if the content was correctly typed
 * @memberOf Cypress.Chainable#
 * @name typeSingleSelectAndCheck
 * @function
 * @param {String} value - Desired value of the element
 * @param {String} selector - Options concerning swSelect usage
 * @param {Object} [options={}] - Options concerning swSelect usage
 */
Cypress.Commands.add('typeSingleSelectAndCheck', {
    prevSubject: 'element'
}, (subject, value, selector, options = {}) => {
    cy.get(subject).typeSingleSelect(value, selector, options);

    // expect the placeholder for an empty select field not be shown and search for the value
    cy.get(`${subject.selector} .sw-select__selection .is--placeholder`).should('not.exist');
    cy.get(`${subject.selector} .sw-select__selection`).contains(value);
});
