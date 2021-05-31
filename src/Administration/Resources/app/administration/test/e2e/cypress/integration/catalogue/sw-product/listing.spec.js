/// <reference types="Cypress" />

const uuid = require('uuid/v4');

describe('Product: Test pagination and the corosponding URL parameters', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        let taxId, currencyId;

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
                })
            }).then(tax => {
                taxId = tax.id;

                cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Euro'
                    },
                    endpoint: 'currency'
                })
            }).then(currency => {
                currencyId = currency.id;

                cy.authenticate();
            }).then(auth => {
                let products = [];
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
                            ]
                        }
                    );
                }
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
                            'entity': 'product',
                            'action': 'upsert',
                            'payload': products
                        }

                    }
                })
            })
    });

    it('@catalogue: check that the url parameters get set', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

        // use the search box and check if term gets set (in the function)
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('product');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Product number',
                propertyName: 'productNumber',
                sortDirection: 'DESC',
                location: 1
            },
            page: 1,
            limit: 25
        });

        cy.log('change Sorting direction from DESC to ASC');
        cy.get('.sw-data-grid__cell--1 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Product number',
                propertyName: 'productNumber',
                sortDirection: 'ASC',
                location: 1
            },
            page: 1,
            limit: 25
        });

        cy.log('change items per page to 10');
        cy.get('#perPage').select("10");
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Product number',
                propertyName: 'productNumber',
                sortDirection: 'ASC',
                location: 1
            },
            page: 1,
            limit: 10
        });
        cy.log('go to second page')
        cy.get(':nth-child(2) > .sw-pagination__list-button').click();
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Product number',
                propertyName: 'productNumber',
                sortDirection: 'ASC',
                location: 1
            },
            page: 2,
            limit: 10
        });

        cy.log('change sorting to Available')
        cy.get('.sw-data-grid__cell--14 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Available',
                propertyName: 'availableStock',
                sortDirection: 'ASC',
                location: 14
            },
            page: 2,
            limit: 10
        });
    });

    it('@catalogue: check that the url parameters get applied after a reload', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index?limit=10&page=2&term=product&sortBy=availableStock&sortDirection=ASC&naturalSorting=true`)

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Available',
                propertyName: 'availableStock',
                sortDirection: 'ASC',
                location: 14
            },
            page: 2,
            limit: 10
        });

        cy.reload();

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Available',
                propertyName: 'availableStock',
                sortDirection: 'ASC',
                location: 14
            },
            page: 2,
            limit: 10
        });
    });
});
