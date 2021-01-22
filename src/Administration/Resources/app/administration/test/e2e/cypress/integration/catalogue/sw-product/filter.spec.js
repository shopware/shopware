// / <reference types="Cypress" />

const uuid = require('uuid/v4');

describe('Product: Testing filter and reset filter', () => {
    before(() => {
        let taxId; let
            currencyId;

        cy.setToInitialState()
            .then(() => {
                cy.createDefaultFixture('tax');
            })
            .then(() => {
                cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Standard rate'
                    },
                    endpoint: 'tax'
                });
            }).then(tax => {
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
                        'write-product': {
                            entity: 'product',
                            action: 'upsert',
                            payload: products
                        }

                    }
                });
            });
    });

    it('@catalogue: check filter function and display listing correctly', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

        cy.onlyOnFeature('FEATURE_NEXT_9831');

        cy.get('.sw-sidebar-navigation-item[title="Filter"]').click();
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('not.exist');

        cy.get('.sw-filter-panel').should('exist');

        // Check if Reset All button shows up
        cy.get('.sw-sidebar-filter-panel__info a').should('not.exist');
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').should('not.exist');

        // Filter results with single criteria
        cy.get('.sw-filter-panel__item').eq(0).find('select').select('true');
        cy.get('.sw-page__smart-bar-amount').contains('26');

        cy.get('.sw-filter-panel__item').eq(0).find('select').select('false');
        cy.get('.sw-page__smart-bar-amount').contains('2');

        // Check notification badge after filtering
        cy.get('.sw-sidebar-navigation-item').eq(1).find('.notification-badge').should('exist');
        cy.get('.sw-sidebar-navigation-item').eq(1).find('.notification-badge').should('have.text', '1');

        // Combine multiple filter criterias
        cy.get('.sw-filter-panel__item').eq(1).find('select').select('true');
        cy.get('.sw-page__smart-bar-amount').contains('1');

        cy.get('.sw-filter-panel__item').eq(1).find('select').select('false');
        cy.get('.sw-page__smart-bar-amount').contains('1');
        cy.get('.sw-sidebar-navigation-item').eq(1).find('.notification-badge').should('have.text', '2');
    });

    it('@catalogue: check reset filter', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

        cy.onlyOnFeature('FEATURE_NEXT_9831');

        cy.get('.sw-sidebar-navigation-item[title="Filter"]').click();

        // Check Reset and Reset All button at default state
        cy.get('.sw-sidebar-filter-panel__info a').should('not.exist');
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').should('not.exist');

        // Check Reset button when filter is active
        cy.get('.sw-filter-panel__item').eq(0).find('select').select('true');
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').should('exist');

        // Click Reset button to reset filter
        cy.get('.sw-filter-panel__item').eq(0).find('.sw-base-filter__reset').click();
        cy.get('.sw-filter-panel__item').eq(0).find('option:selected').should('have.value', '');

        // Reset All button should show up when there is active filter
        cy.get('.sw-filter-panel__item').eq(1).find('select').select('true');
        cy.get('.sw-sidebar-item__headline a').should('exist');

        // Click Reset All button
        cy.get('.sw-sidebar-item__headline a').click();
        cy.get('.sw-sidebar-item__headline a').should('not.exist');
        cy.get('.sw-sidebar-navigation-item[title="Filter"]').find('.notification-badge').should('not.exist');
        cy.get('.sw-page__smart-bar-amount').contains('28');
    });
});
