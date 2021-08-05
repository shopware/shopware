// / <reference types="Cypress" />

const uuid = require('uuid/v4');

describe('Product: Testing filter and reset filter', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        let taxId; let
            currencyId;
        let userId;

        cy.setToInitialState()
            .then(() => {
                cy.createDefaultFixture('tax');
            })
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
                cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Standard rate'
                    },
                    endpoint: 'tax'
                });
            })
            .then(tax => {
                taxId = tax.id;

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

                cy.authenticate();
            })
            .then(auth => {
                const products = [];
                for (let i = 1; i <= 26; i++) {
                    products.push(
                        {
                            name: `product-${i}`,
                            stock: i,
                            productNumber: uuid().replace(/-/g, ''),
                            taxId: taxId,
                            price: [
                                {
                                    currencyId: currencyId,
                                    net: 42,
                                    linked: false,
                                    gross: 64
                                }
                            ],
                            cover: {
                                media: {
                                    url: 'http://shopware.com/image1.jpg',
                                    alt: 'Lorem Ipsum dolor'
                                }
                            }
                        }
                    );
                }

                products.push(
                    {
                        name: 'product-27',
                        stock: 27,
                        productNumber: uuid().replace(/-/g, ''),
                        taxId: taxId,
                        active: false,
                        price: [
                            {
                                currencyId: currencyId,
                                net: 42,
                                linked: false,
                                gross: 64
                            }
                        ],
                        cover: {
                            media: {
                                url: 'http://shopware.com/image1.jpg',
                                alt: 'Lorem Ipsum dolor'
                            }
                        }
                    }
                );

                products.push(
                    {
                        name: 'product-27',
                        stock: 27,
                        productNumber: uuid().replace(/-/g, ''),
                        taxId: taxId,
                        active: false,
                        price: [
                            {
                                currencyId: currencyId,
                                net: 42,
                                linked: false,
                                gross: 64
                            }
                        ]
                    }
                );

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
                        'write-product': {
                            entity: 'product',
                            action: 'upsert',
                            payload: products
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
                                    key: 'grid.filter.product',
                                    updatedAt: '2021-01-21T06:54:00.252+00:00',
                                    userId: userId,
                                    value: {
                                        'active-filter': {
                                            value: 'true',
                                            criteria: [{ type: 'equals', field: 'active', value: true }]
                                        },
                                        'product-without-images-filter': {
                                            value: 'true',
                                            criteria: [{
                                                type: 'not',
                                                queries: [{ type: 'equals', field: 'media.id', value: null }],
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
    it.skip('@catalogue: check filter function and display listing correctly', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post'
        }).as('filterProduct');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('getUserConfig');

        cy.wait('@filterProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();

        // Check if saved user filter is loaded
        cy.wait('@getUserConfig').then((xhr) => {
            cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '2');
            // Check if Reset All button shows up
            cy.get('.sw-sidebar-item__headline a').should('exist');
            cy.get('#active-filter').find('.sw-base-filter__reset').should('exist');
        });

        cy.get('.sw-filter-panel').should('exist');

        cy.get('.sw-sidebar-item__headline a').click();

        cy.wait('@filterProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Filter results with single criteria
        cy.get('#active-filter').find('select').select('true');
        cy.wait('@filterProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('26');

        cy.get('#active-filter').find('select').select('false');
        cy.wait('@filterProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('2');

        // Check notification badge after filtering
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '1');

        // Combine multiple filter criterias
        cy.get('#product-without-images-filter').find('select').select('true');
        cy.wait('@filterProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('1');

        cy.get('#product-without-images-filter').find('select').select('false');
        cy.wait('@filterProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__smart-bar-amount').contains('1');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('have.text', '2');
    });

    // TODO skipped due to flakiness, see NEXT-15697
    it.skip('@catalogue: check reset filter', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post'
        }).as('filterProduct');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('getUserConfig');

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();

        cy.get('.sw-sidebar-item__headline a').click();

        // Check Reset button when filter is active
        cy.get('#active-filter').find('select').select('true');
        cy.get('#active-filter').find('.sw-base-filter__reset').should('exist');

        // Click Reset button to reset filter
        cy.get('#active-filter').find('.sw-base-filter__reset').click();

        cy.wait('@getUserConfig').then((xhr) => {
            cy.get('#active-filter').find('option:selected').should('have.value', '');
        });

        // Reset All button should show up when there is active filter
        cy.get('#product-without-images-filter').find('select').select('true');
        cy.get('.sw-sidebar-item__headline a').should('exist');

        // Click Reset All button
        cy.get('.sw-sidebar-item__headline a').click();
        cy.get('.sw-sidebar-item__headline a').should('not.exist');
        cy.get('.sw-sidebar-navigation-item[title="Filters"]').find('.notification-badge').should('not.exist');

        cy.get('.sw-loader').should('not.exist');

        cy.wait('@getUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.wait('@filterProduct').then((xhr) => {
                expect(xhr).to.have.property('status', 200);
                cy.contains('.sw-page__smart-bar-amount', '28');
            });
        });
    });
});
