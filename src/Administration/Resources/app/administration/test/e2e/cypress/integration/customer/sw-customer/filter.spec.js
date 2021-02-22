// / <reference types="Cypress" />
const uuid = require('uuid/v4');

describe('Customer: Test filter and reset filter', () => {
    before(() => {
        let countryId, paymentMethodId, salesChannelId, groupId, salutationId;
        cy.setToInitialState().then(() => {
            cy.searchViaAdminApi({
                endpoint: 'country', data: {
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
                })
            }).then(data => {
                paymentMethodId = data.id
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel', data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                })
            }).then(data => {
                salesChannelId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'customer-group', data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Standard customer group'
                    }
                })
            }).then(data => {
                groupId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'salutation', data: {
                        field: 'displayName',
                        type: 'equals',
                        value: 'Mr.'
                    }
                })
            }).then(data => {
                salutationId = data.id;
                return cy.authenticate()
            }).then(auth => {

                let customers = [];
                for (let i = 1; i <= 26; i++) {
                    const standInId = uuid().replace(/-/g, '');
                    customers.push(
                        {
                            firstName: 'Pep',
                            lastName: `Eroni-${i}`,
                            defaultPaymentMethodId: paymentMethodId,
                            defaultBillingAddressId: standInId,
                            defaultShippingAddressId: standInId,
                            customerNumber: uuid().replace(/-/g, ''),
                            email: `test-${i}@example.com`
                        }
                    );
                }

                customers.push(
                    {
                        firstName: 'Pepper',
                        lastName: `Eroni-27`,
                        defaultPaymentMethodId: paymentMethodId,
                        defaultBillingAddressId: uuid().replace(/-/g, ''),
                        defaultShippingAddressId: uuid().replace(/-/g, ''),
                        customerNumber: uuid().replace(/-/g, ''),
                        email: `test-27@example.com`,
                        active: false
                    }
                );

                customers = customers.map(customer => Object.assign({ countryId, salesChannelId, salutationId, groupId }, customer));
                return cy.request({
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
                            'entity': 'customer',
                            'action': 'upsert',
                            'payload': customers
                        }

                    }
                })
            });
        });

    })

    it('@Customer: check filter function and display list correctly', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);

        cy.onlyOnFeature('FEATURE_NEXT_9831');

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

        cy.get('.sw_sidebar__navigation-list li').eq(1).click();
        cy.get('.sw_sidebar__navigation-list li').eq(1).find('button[title="Filter"]').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('not.exist');

        cy.get('.sw-filter-panel').should('exist');

        // Check if Reset All button shows up
        cy.get('.sw-sidebar-item__headline a').should('not.exist');
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').should('not.exist');

        // Filter results with single criteria
        cy.get('.sw-filter-panel__item').eq(0).find('input').click();
        cy.get('.sw-select-result-list__item-list li').contains('Mr.').click();

        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('27');

        // Check notification badge after filtering
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('have.text', '1');

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

        cy.log('change Sorting direction from DESC to ASC')
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
        cy.get('.sw-filter-panel__item').eq(1).find('select').select('true');
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('26');

        cy.get('.sw-filter-panel__item').eq(1).find('select').select('false');
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('1');

        cy.get('.sw-filter-panel__item').eq(2).find('input').click();
        cy.wait('@getPaymentMethod').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-select-result-list__item-list li').contains('Invoice').click();
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('1');

        // Check notification badge after filtering with multiple filters criteria
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('have.text', '3');
    });

    it('@Customer: check reset filter and reset all filter', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);

        cy.onlyOnFeature('FEATURE_NEXT_9831');

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer`,
            method: 'post'
        }).as('filterCustomer');

        cy.get('.sw_sidebar__navigation-list li').eq(1).click();
        cy.get('.sw_sidebar__navigation-list li').eq(1).find('button[title="Filter"]').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('not.exist');

        cy.get('.sw-filter-panel').should('exist');

        // Check Reset and Reset All button at default state
        cy.get('.sw-sidebar-item__headline a').should('not.exist');
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').should('not.exist');

        // Check Reset button when filter is active
        cy.get('.sw-filter-panel__item').eq(0).find('input').click();
        cy.get('.sw-select-result-list__item-list li').contains('Mr.').click();
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').should('exist');

        // Click Reset button to reset filter
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').click();
        cy.get('.sw-filter-panel__item').eq(0).find('li.sw-select-selection-list__item-holder').should('not.exist');

        // Reset All button should show up when there is active filter
        cy.get('.sw-filter-panel__item').eq(1).find('select').select('true');
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('26');
        cy.get('.sw-sidebar-item__headline a').should('exist');

        // Click Reset All button
        cy.get('.sw-sidebar-item__headline a').click();
        cy.get('.sw-sidebar-item__headline a').should('not.exist');
        cy.wait('@filterCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('27');
    });
});
