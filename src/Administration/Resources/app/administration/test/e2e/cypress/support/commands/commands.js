import '@percy/cypress';

/**
 * Types in the global search field and verify search terms in url
 * @memberOf Cypress.Chainable#
 * @name typeAndCheckSearchField
 * @function
 * @param {String} value - The value to type
 */
Cypress.Commands.add('typeAndCheckSearchField', {
    prevSubject: 'element',
}, (subject, value) => {
    // Request we want to wait for later
    cy.server();
    cy.route({
        url: `${Cypress.env('apiPath')}/search/**`,
        method: 'post',
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
    prevSubject: false,
}, (permissions) => {
    cy.window().then(($w) => {
        const roleID = 'ef68f039468d4788a9ee87db9b3b94de';
        const localeId = $w.Shopware.State.get('session').currentUser.localeId;
        let headers = {
            Accept: 'application/vnd.api+json',
            Authorization: `Bearer ${$w.Shopware.Context.api.authToken.access}`,
            'Content-Type': 'application/json',
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
                password: 'shopware',
            },
        }).then(response => {
            // overwrite headers with new scope
            headers = {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${response.body.access_token}`,
                'Content-Type': 'application/json',
            };

            return cy.request({
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
        }).then(() => {
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
                    id: 'b7fb49e9d86d4e5b9b03c9d6f929e36b',
                    lastName: 'Muster',
                    localeId: localeId,
                    password: 'Passw0rd!',
                    username: 'maxmuster',
                },
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
            'Content-Type': 'application/json',
        };

        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        }).then((data) => {
            customerId = data.id;

            return cy.searchViaAdminApi({
                endpoint: 'product',
                data: {
                    field: 'name',
                    value: 'Product name',
                },
            });
        }).then((data) => {
            productId = data.id;

            return cy.searchViaAdminApi({
                endpoint: 'sales-channel',
                data: {
                    field: 'name',
                    value: 'Storefront',
                },
            });
        })
            .then((data) => {
                salesChannelId = data.id;

                return cy.searchViaAdminApi({
                    endpoint: 'language',
                    data: {
                        field: 'name',
                        value: 'English',
                    },
                });
            })
            .then((data) => {
                cy.request({
                    url: '/api/product-review',
                    method: 'POST',
                    headers: headers,
                    body: Cypress._.merge(reviewJson, {
                        customerId: customerId,
                        languageId: data.id,
                        productId: productId,
                        salesChannelId: salesChannelId,
                    }),
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
            value: salesChannel,
        },
    }).then((data) => {
        salesChannelId = data.id;

        return cy.searchViaAdminApi({
            endpoint: 'shipping-method',
            data: {
                field: 'name',
                value: name,
            },
        });
    }).then((data) => {
        return cy.updateViaAdminApi('sales-channel', salesChannelId, {
            data: {
                shippingMethodId: data.id,
            },
        });
    });
});

/**
 * Updates an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name updateViaAdminApi
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
 * @name sortAndCheckListingAscViaColumn
 * @function
 * @param {String} columnTitle - Title of the column to sort with
 * @param {String} firstEntry - String of the first entry to be in listing after sorting
 * @param {String} [rowZeroSelector = .sw-data-grid__row--0]  - Name of the sales channel
 */
Cypress.Commands.add('sortAndCheckListingAscViaColumn', (
    columnTitle,
    firstEntry,
    rowZeroSelector = '.sw-data-grid__row--0',
) => {
    // Sort listing
    cy.contains('.sw-data-grid__cell-content', columnTitle).should('be.visible');
    cy.contains('.sw-data-grid__cell-content', columnTitle).click();

    // Assertions to make sure listing is loaded
    cy.get('.sw-data-grid__skeleton').should('not.exist');
    cy.get('.sw-loader').should('not.exist');

    // Assertions to make sure sorting was applied
    cy.get('.sw-data-grid__sort-indicator').should('be.visible');
    cy.get('.icon--small-arrow-small-down').should('not.exist');
    cy.get('.icon--small-arrow-small-up').should('be.visible');
    cy.get(rowZeroSelector).should('be.visible');
    cy.contains(rowZeroSelector, firstEntry).should('be.visible');
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
Cypress.Commands.add('testListing', ({ searchTerm, sorting = {
    location: undefined,
    text: undefined,
    propertyName: undefined,
    sortDirection: undefined,
}, page, limit, changesUrl = true }) => {
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
    cy.get(`.sw-data-grid__cell--${sorting.location} > .sw-data-grid__cell-content`).get(iconClass)
        .should('be.visible');

    // check page
    cy.get(`:nth-child(${page}) > .sw-pagination__list-button`).should('have.class', 'is-active');

    // check limit
    cy.get('#perPage').contains(limit);
    // here we have to add 1 because the <th> has the same class
    cy.get('.sw-data-grid__row').should('have.length', (limit + 1));
});


// TODO: this should be moved into the "e2e-testsuite-platform" plugin
//  (open MR: https://github.com/shopware/e2e-testsuite-platform/pull/99 )
/**
 * Types in a sw-multi-select field all the specified values and checks if the content was correctly set.
 * @memberOf Cypress.Chainable#
 * @name typeMultiSelectAndCheckMultiple
 * @function
 * @param {String[]} values - Desired values of the element
 */
Cypress.Commands.add(
    'typeMultiSelectAndCheckMultiple',
    {
        prevSubject: 'element',
    },
    (subject, values) => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/*`,
            method: 'post',
        }).as('filteredResultCall');

        cy.wrap(subject)
            .scrollIntoView() // try to make it visible so it does not error out if it is not in view
            .should('be.visible');

        // type in each value and select it
        for (let i = 0; i < values.length; i += 1) {
            cy.get(`${subject.selector} .sw-select-selection-list__input`)
                .clear()
                .type(values[i])
                .should(
                    'have.value',
                    values[i],
                );

            // wait for the first request (which happens on opening / clicking in the input
            cy.wait('@filteredResultCall').then(() => {
                // wait for the second request (which happens on stop typing with the actual search)
                cy.wait('@filteredResultCall').then(() => {
                    cy.get('.sw-loader__element').should('not.exist');
                });
            });

            // select the value
            cy.contains('.sw-select-result-list__content .sw-select-result', values[i])
                .should('be.visible')
                .click();
        }

        // close search results
        cy.get(`${subject.selector} .sw-select-selection-list__input`).type('{esc}');
        cy.get(`${subject.selector} .sw-select-result-list`).should(
            'not.exist',
        );

        // check if all values are selected
        for (let i = 0; i < values.length; i += 1) {
            cy.get(`${subject.selector} .sw-select-selection-list`)
                .should('contain', values[i]);
        }

        // return same element as the one this command works on so it can be chained with other commands.
        // otherwise it will return the last element which is in this case a '.sw-select-selection-list' element.
        cy.wrap(subject);
    },
);

Cypress.Commands.add(
    'clickMainMenuItem',
    ({ targetPath, mainMenuId, subMenuId = null }) => {
        const finalMenuItem = `.sw-admin-menu__item--${mainMenuId}`;

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

                    // the admin menu sometimes replaces the dom element. So we wait for some time
                    cy.wait(500);
                    cy.get(`.sw-admin-menu__item--${mainMenuId} .sw-admin-menu__navigation-list-item.${subMenuId}`)
                        .should('be.visible');

                    cy.get(`.sw-admin-menu__item--${mainMenuId} .sw-admin-menu__navigation-list-item.${subMenuId}`)
                        .click();
                } else {
                    cy.get(finalMenuItem).should('be.visible').click();
                }
            });
        cy.url().should('include', targetPath);
    },
);

Cypress.Commands.add('getAttached', selector => {
    const getElement = typeof selector === 'function' ? selector : $d => $d.find(selector);
    let $el = null;

    return cy.document().should($d => {
        $el = getElement(Cypress.$($d));

        // eslint-disable-next-line no-unused-expressions
        expect(Cypress.dom.isDetached($el)).to.be.false;
    }).then(() => cy.wrap($el));
});

/**
 * Creates a variant product based on given fixtures "product-variants.json", 'tax,json" and "property.json"
 * with minor customisation
 * @memberOf Cypress.Chainable#
 * @name createProductVariantFixture
 * @function
 */
Cypress.Commands.add('createProductVariantFixture', () => {
    return cy.createDefaultFixture('tax', {
        id: '91b5324352dc4ee58ec320df5dcf2bf4',
    }).then(() => {
        return cy.createPropertyFixture({
            options: [{
                id: '15532b3fd3ea4c1dbef6e9e9816e0715',
                name: 'Red',
            }, {
                id: '98432def39fc4624b33213a56b8c944d',
                name: 'Green',
            }],
        });
    }).then(() => {
        return cy.createPropertyFixture({
            name: 'Size',
            options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }],
        });
    }).then(() => {
        return cy.searchViaAdminApi({
            data: {
                field: 'name',
                value: 'Storefront',
            },
            endpoint: 'sales-channel',
        });
    })
        .then((saleschannel) => {
            cy.createDefaultFixture('product', {
                visibilities: [{
                    visibility: 30,
                    salesChannelId: saleschannel.id,
                }],
            }, 'product-variants.json');
        });
});

/**
 * Ensures Shopware's modals are fully loaded before a snapshot is taken
 * @memberOf Cypress.Chainable#
 * @name handleModalSnapshot
 * @param {String} title - Modal title
 * @function
 */
Cypress.Commands.add('handleModalSnapshot', (title) => {
    cy.contains('.sw-modal__header', title).should('be.visible');

    cy.get('.sw-modal').should('be.visible').then(() => {
        cy.get('.sw-modal-fade-enter-active').should('not.exist');
        cy.get('.sw-modal-fade-enter').should('not.exist');
    }).then(() => {
        cy.get('.sw-modal-fade-leave-active').should('not.exist');
        cy.get('.sw-modal-fade-leave-to').should('not.exist');
    })
        .then(() => {
            cy.get('.sw-modal').should('have.css', 'opacity', '1');
        });
});

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
