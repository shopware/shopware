// / <reference types="Cypress" />

const uuid = require('uuid/v4');

describe('Order: Testing filter and reset filter', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        let currencyId; let countryId; let paymentMethodId; let salesChannelId; let groupId; let salutationId; let stateMachineId; let
            shippingMethodId; let userId;

        cy.setToInitialState()
            .then(() => {
                cy.searchViaAdminApi({
                    data: {
                        field: 'username',
                        value: 'admin'
                    },
                    endpoint: 'user'
                });
            })
            .then((user) => {
                userId = user.id;
                return cy.searchViaAdminApi({
                    endpoint: 'state-machine',
                    data: {
                        field: 'technicalName',
                        type: 'equals',
                        value: 'order.state'
                    }
                });
            })
            .then((data) => {
                stateMachineId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'country',
                    data: {
                        field: 'iso',
                        type: 'equals',
                        value: 'DE'
                    }
                });
            })
            .then(data => {
                countryId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'payment-method',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Invoice'
                    }
                });
            })
            .then(data => {
                paymentMethodId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'shipping-method',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Standard'
                    }
                });
            })
            .then(data => {
                shippingMethodId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                });
            })
            .then(data => {
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

                cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Euro'
                    },
                    endpoint: 'currency'
                });
            })
            .then(currency => {
                currencyId = currency.id;

                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        value: 'Storefront'
                    }
                });
            })
            .then(salesChannel => {
                salesChannelId = salesChannel.id;

                cy.authenticate();
            })
            .then(auth => {
                const orders = [];
                for (let i = 1; i < 10; i += 1) {
                    orders.push(
                        {
                            name: `order-${i}`,
                            orderNumber: uuid().replace(/-/g, ''),
                            billingAddressId: uuid().replace(/-/g, ''),
                            currencyId: currencyId,
                            salesChannelId: salesChannelId,
                            stateId: uuid().replace(/-/g, ''),
                            orderDateTime: new Date(),
                            orderCustomer: {
                                firstName: 'John',
                                lastName: `Doe ${i}`,
                                email: 'johndoe@shopware.com',
                                salutationId: salutationId,
                                defaultPaymentMethodId: paymentMethodId,
                                defaultBillingAddressId: uuid().replace(/-/g, ''),
                                defaultShippingAddressId: uuid().replace(/-/g, '')
                            },
                            billingAddress: {
                                countryId,
                                salutationId,
                                firstName: 'John',
                                lastName: 'Doe',
                                street: 'Test street',
                                city: 'Test city',
                                zipcode: '000-0000'
                            },
                            stateMachineState: {
                                name: `order state ${i}`,
                                technicalName: `order.${i}`,
                                stateMachineId: stateMachineId
                            },
                            transactions: [
                                {
                                    paymentMethodId,
                                    shippingMethodId,
                                    amount: {
                                        quantity: 1,
                                        taxRules: [{ taxRate: 19.0, percentage: 10.110842397398 }],
                                        listPrice: null,
                                        unitPrice: 0.0,
                                        totalPrice: 0.0,
                                        referencePrice: null,
                                        calculatedTaxes: [{ tax: 0.0, price: 0.0, taxRate: 19.0 }]
                                    },
                                    stateMachineState: {
                                        name: `payment state ${i}`,
                                        technicalName: `payment.${i}`,
                                        stateMachineId: stateMachineId
                                    }
                                }
                            ],
                            deliveries: [
                                {
                                    paymentMethodId,
                                    shippingMethodId,
                                    shippingDateEarliest: new Date(),
                                    shippingDateLatest: new Date(),
                                    shippingCosts: {
                                        quantity: 1,
                                        taxRules: [{ taxRate: 19.0, percentage: 10.110842397398 }],
                                        listPrice: null,
                                        unitPrice: 0.0,
                                        totalPrice: 0.0,
                                        referencePrice: null,
                                        calculatedTaxes: [{ tax: 0.0, price: 0.0, taxRate: 19.0 }]
                                    },
                                    stateMachineState: {
                                        name: `shipping state ${i}`,
                                        technicalName: `shipping.${i}`,
                                        stateMachineId: stateMachineId
                                    }
                                }
                            ],
                            price: {
                                netPrice: 8294.66,
                                rawTotal: 8454.01,
                                taxRules: [{ taxRate: 19.0, percentage: 100.0 }],
                                taxStatus: 'net',
                                totalPrice: 8454.01,
                                positionPrice: 8294.66,
                                calculatedTaxes: [{ tax: 159.35, price: 838.66, taxRate: 19.0 }]
                            },
                            shippingCosts: {
                                quantity: 1,
                                taxRules: [{ taxRate: 19.0, percentage: 10.110842397398 }],
                                listPrice: null,
                                unitPrice: 0.0,
                                totalPrice: 0.0,
                                referencePrice: null,
                                calculatedTaxes: [{ tax: 0.0, price: 0.0, taxRate: 19.0 }]
                            },
                            currencyFactor: 1.25
                        }
                    );
                }

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
                        'write-order': {
                            entity: 'order',
                            action: 'upsert',
                            payload: orders
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
                                    key: 'grid.filter.order',
                                    updatedAt: '2021-01-21T06:54:00.252+00:00',
                                    userId: userId,
                                    value: {
                                        'document-filter': {
                                            value: 'true',
                                            criteria: [{
                                                type: 'not',
                                                queries: [{
                                                    type: 'equals',
                                                    field: 'documents.id',
                                                    value: null
                                                }],
                                                operator: 'AND'
                                            }]
                                        }
                                    }
                                }
                            ]
                        }
                    }
                });
            });
    });

    // TODO skipped due to flakiness, see NEXT-15697
    it.skip('@order: check filter function and display listing correctly', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('filterOrder');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/state-machine-state`,
            method: 'post'
        }).as('getStateMachineState');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('getUserConfig');

        cy.wait('@filterOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();

        // Check if saved user filter is loaded
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '1');

        // Check if Reset All button shows up
        cy.get('.sw-sidebar-item__headline a').should('exist');
        cy.get('#document-filter').find('.sw-base-filter__reset').should('exist');

        cy.get('.sw-filter-panel').should('exist');

        cy.get('.sw-sidebar-item__headline a').click();

        cy.wait('@filterOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Filter results with single criteria
        cy.get('#document-filter').find('select').select('true');
        cy.wait('@filterOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('0');

        cy.get('#document-filter').find('select').select('false');
        cy.wait('@filterOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Check notification badge after filtering
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('exist');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '1');

        // Combine multiple filter criterias
        cy.get('#status-filter .sw-entity-multi-select').scrollIntoView();
        cy.get('#status-filter .sw-entity-multi-select').typeMultiSelectAndCheck('order state 1', { searchTerm: 'order state 1' });

        cy.get('.sw-page__smart-bar-amount').contains('1');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '2');
    });

    // TODO skipped due to flakiness, see NEXT-15697
    it.skip('@order: check reset filter', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/search/state-machine-state`,
            method: 'post'
        }).as('getStateMachineState');

        // Assert the grid has finished loading and the preset filters are active
        cy.get('.sw-data-grid__body').should('not.have.attr', 'aria-busy');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('be.visible');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('getUserConfig');

        // Open the filter panel
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();

        cy.wait('@getUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('filterOrder');

        // Reset all filters
        cy.get('.sw-sidebar-item__headline a').click();

        cy.wait('@filterOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.requestBody).not.to.have.property('filter');
        });

        cy.get('.sw-sidebar-item__headline a').should('not.be.visible');

        // Check Reset button when filter is active
        cy.get('#document-filter').find('select').select('true');
        cy.get('#document-filter').find('.sw-base-filter__reset').scrollIntoView().should('be.visible');

        // Click Reset button to reset filter
        cy.get('#document-filter').find('.sw-base-filter__reset').click();

        // Assert the grid has finished loading and no filters are active
        cy.get('.sw-data-grid__body').should('not.have.attr', 'aria-busy');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('not.be.visible');

        // Reset All button should show up when there is active filter
        cy.get('#status-filter .sw-entity-multi-select').scrollIntoView();
        cy.get('#status-filter .sw-entity-multi-select').typeMultiSelectAndCheck('order state 2', { searchTerm: 'order state 2' });

        // Assert the grid has finished loading and the chosen filters are active
        cy.get('.sw-data-grid__body').should('not.have.attr', 'aria-busy');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('be.visible');

        cy.get('.sw-sidebar-item__headline a').should('be.visible');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('filterOrder');

        // Click Reset All button
        cy.get('.sw-sidebar-item__headline a').click();

        cy.wait('@filterOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.requestBody).not.to.have.property('filter');
        });

        cy.get('.sw-sidebar-item__headline a').should('not.be.visible');

        cy.get('.sw-page__smart-bar-amount').contains('9');
    });
});
