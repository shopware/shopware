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
        }).then(() => {
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
        return cy.window().then(() => {
            cy.setCookie('bearerAuth', JSON.stringify(result));

            // Return bearer token
            return cy.getCookie('bearerAuth');
        }).then(() => {
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
 * Test wheter the searchTerm, sorting, page and limt get applied to the URL and the listing
 * @memberOf Cypress.Chainable#
 * @name testListing
 * @function
 * @param {String} searchTerm - the searchTerm for witch should be searched for
 * @param {Object} sorting - the sorting to be checked
 * @param {Number} sorting.location - the column in wich the number is
 * @param {String} sorting.text - the text in the column header
 * @param {String} sorting.propertyName - the 'technical' name for the column
 * @param {('ASC'|'DESC')} sorting.sortDirection - the sort direction
 * @param {Number} page - the page to be checked
 * @param {Number} limit - the limit to be checked
 * @param {boolean} changesUrl - wheter changing the sorting or page updates the URL

 */
Cypress.Commands.add('testListing', ({ searchTerm, sorting = { location: undefined, text: undefined, propertyName: undefinded, sortDirection: undefined }, page, limit, changesUrl = true }) => {
    cy.get('.sw-loader').should('not.exist');
    cy.get('.sw-data-grid__skeleton').should('not.exist');

    // check searchterm if supplied
    if (searchTerm) {
        cy.url().should('contain', `term=${searchTerm}`);
        cy.get('.sw-search-bar__input').should('have.value', searchTerm);
    }

    // determine what icon class should be displayed
    let iconClass;
    switch (sorting.sortDirection) {
        case 'ASC':
            iconClass = '.icon--small-arrow-small-up';
            break;
        case 'DESC':
            iconClass = '.icon--small-arrow-small-down';
            break;
        default:
            throw new Error(`${sorting.sortDirection} is not a valid sorting direction`);
    }

    if (changesUrl) {
        cy.url().should('contain', `sortBy=${sorting.propertyName}`);
        cy.url().should('contain', `sortDirection=${sorting.sortDirection}`);
        cy.url().should('contain', `page=${page}`);
        cy.url().should('contain', `limit=${limit}`);
    }

    // check sorting
    cy.get(`.sw-data-grid__cell--${sorting.location} > .sw-data-grid__cell-content`).contains(sorting.text);
    cy.get(`.sw-data-grid__cell--${sorting.location} > .sw-data-grid__cell-content`).get(iconClass).should('be.visible');

    // check page
    cy.get(`:nth-child(${page}) > .sw-pagination__list-button`).should('have.class', 'is-active');

    // check limit
    cy.get('#perPage').contains(limit);
    // here we have to add 1 because the <th> has the same class
    cy.get('.sw-data-grid__row').should('have.length', (limit + 1));
});
