// / <reference types="Cypress" />
const uuid = require('uuid/v4');

describe('Customer: Test filter and reset filter', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        let countryId; let paymentMethodId; let salesChannelId; let groupId; let salutationId; let
            userId;
        cy.setToInitialState().then(() => {
            cy.searchViaAdminApi({
                data: {
                    field: 'username',
                    value: 'admin'
                },
                endpoint: 'user'
            });
        }).then((user) => {
            userId = user.id;
            cy.searchViaAdminApi({
                endpoint: 'country',
                data: {
                    field: 'iso',
                    type: 'equals',
                    value: 'DE'
                }
            }).then(data => {
                countryId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'payment-method',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Invoice'
                    }
                });
            }).then(data => {
                paymentMethodId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                });
            }).then(data => {
                salesChannelId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'customer-group',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Standard customer group'
                    }
                });
            })
                .then(data => {
                    groupId = data.id;
                    return cy.searchViaAdminApi({
                        endpoint: 'salutation',
                        data: {
                            field: 'displayName',
                            type: 'equals',
                            value: 'Mr.'
                        }
                    });
                })
                .then(data => {
                    salutationId = data.id;
                    return cy.authenticate();
                })
                .then(auth => {
                    let customers = [];
                    for (let i = 1; i <= 26; i++) {
                        const standInId = uuid().replace(/-/g, '');
                        customers.push({
                            firstName: 'Pep',
                            lastName: `Eroni-${i}`,
                            defaultPaymentMethodId: paymentMethodId,
                            defaultBillingAddressId: standInId,
                            defaultShippingAddressId: standInId,
                            customerNumber: uuid().replace(/-/g, ''),
                            email: `test-${i}@example.com`
                        });
                    }

                    customers.push({
                        firstName: 'Pepper',
                        lastName: 'Eroni-27',
                        defaultPaymentMethodId: paymentMethodId,
                        defaultBillingAddressId: uuid().replace(/-/g, ''),
                        defaultShippingAddressId: uuid().replace(/-/g, ''),
                        customerNumber: uuid().replace(/-/g, ''),
                        email: 'test-27@example.com',
                        active: false
                    });

                    customers = customers.map(customer => Object.assign({ countryId, salesChannelId, salutationId, groupId }, customer));
                    cy.request({
                        headers: {
                            Accept: 'application/vnd.api+json',
                            Authorization: `Bearer ${auth.access}`,
                            'Content-Type': 'application/json'
                        },
                        method: 'POST',
                        url: '/api/_action/sync',
                        qs: {
                            response: true
                        },
                        body: {
                            'write-customer': {
                                entity: 'customer',
                                action: 'upsert',
                                payload: customers
                            }
                        }
                    });

                    cy.request({
                        headers: {
                            Accept: 'application/vnd.api+json',
                            Authorization: `Bearer ${auth.access}`,
                            'Content-Type': 'application/json'
                        },
                        method: 'POST',
                        url: '/api/_action/sync',
                        qs: {
                            response: true
                        },
                        body: {
                            'write-user-config': {
                                entity: 'user_config',
                                action: 'upsert',
                                payload: [
                                    {
                                        createdAt: '2021-01-21T06:52:41.857+00:00',
                                        id: '021150d043ee49e18642daef58e92c96',
                                        key: 'grid.filter.customer',
                                        updatedAt: '2021-01-21T06:54:00.252+00:00',
                                        userId: userId,
                                        value: {
                                            'salutation-filter': {
                                                value: [{
                                                    id: 'adada1a3529b491284b550a80932ab58'
                                                }],
                                                criteria: [{
                                                    type: 'equalsAny',
                                                    field: 'salutation.id',
                                                    value: 'adada1a3529b491284b550a80932ab58'
                                                }]
                                            },
                                            'account-status-filter': {
                                                value: 'true',
                                                criteria: [{ type: 'equals', field: 'active', value: true }]
                                            }
                                        }
                                    }
                                ]
                            }
                        }
                    });
                });
        });
    });

    // TODO skipped due to flakiness, see NEXT-15697
    it.skip('@customer: check filter function and display list correctly', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer`,
            method: 'post'
        }).as('filterCustomer');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/payment-method`,
            method: 'post'
        }).as('getPaymentMethod');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('getUserConfig');

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').should('exist');

        // Check if saved user filter is loaded
        cy.wait('@getUserConfig').then(() => {
            cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '2');
            // Check if Reset All button shows up
            cy.get('.sw-sidebar-item__headline a').should('exist');
        });

        cy.get('.sw-filter-panel').should('exist');

        cy.get('.sw-sidebar-item__headline a').click();

        // Check Reset button when filter is active
        cy.get('#salutation-filter .sw-entity-multi-select').scrollIntoView();
        cy.get('#salutation-filter .sw-entity-multi-select').typeMultiSelectAndCheck('Mr.', { searchTerm: 'Mr.' });

        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('27');

        // Check notification badge after filtering
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '1');

        cy.testListing({
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'DESC',
                location: 4
            },
            page: 1,
            limit: 25
        });

        cy.log('change Sorting direction from DESC to ASC');
        cy.get('.sw-data-grid__cell--4 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'ASC',
                location: 4
            },
            page: 1,
            limit: 25
        });

        // Combine multiple filters criteria
        cy.get('#account-status-filter').find('select').select('true');
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('26');

        // Check notification badge after filtering with multiple filters criteria
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '2');
    });

    // TODO skipped due to flakiness, see NEXT-15697
    it.skip('@customer: check reset filter and reset all filter', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer`,
            method: 'post'
        }).as('filterCustomer');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('getUserConfig');

        cy.route({
            url: `${Cypress.env('apiPath')}/user-config/*`,
            method: 'patch'
        }).as('patchUserConfig');

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').should('exist');

        // Check if saved user filter is loaded
        cy.wait('@getUserConfig').then(() => {
            cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('exist');
        });

        cy.get('.sw-filter-panel').should('exist');

        cy.get('.sw-sidebar-item__headline a').click();

        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);

            // Check Reset button when filter is active
            cy.get('#salutation-filter .sw-entity-multi-select').scrollIntoView();
            cy.get('#salutation-filter .sw-entity-multi-select').typeMultiSelectAndCheck('Mr.', { searchTerm: 'Mr.' });

            cy.get('#salutation-filter').find('.sw-base-filter__reset').should('exist');

            // Click Reset button to reset filter
            cy.get('#salutation-filter').find('.sw-base-filter__reset').click();

            return cy.wait('@filterCustomer');
        }).then(() => {
            cy.get('#salutation-filter').find('li.sw-select-selection-list__item-holder').should('not.exist');

            // Reset All button should show up when there is active filter
            cy.get('#account-status-filter').find('select').select('true');
            cy.get('.sw-sidebar-item__headline a').should('exist');

            // Click Reset All button
            cy.get('.sw-sidebar-item__headline a').click();
            cy.get('.sw-sidebar-item__headline a').should('not.exist');
        });
    });
});
