// / <reference types="Cypress" />
const uuid = require('uuid/v4');


describe('Review: Test pagination and the corosponding URL parameters', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        let authToken = undefined;
        let salesChannelId = undefined;
        let languageId = undefined;
        let productIds = [];

        cy.setToInitialState().then(() => {
                return cy.authenticate()
            }).then((auth) => {
                authToken = auth.access;

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
                let products = [];

                for (let i = 1; i <= 26; i++) {
                    products.push(
                        {
                            name: `product-${i}`,
                            stock: i,
                            productNumber: uuid().replace(/-/g, ''),
                            taxId: tax.id,
                            price: [
                                {
                                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                                    net: 1,
                                    linked: false,
                                    gross: 1
                                }
                            ]
                        }
                    );
                }
                return cy.request({
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${authToken}`,
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
            }).then(products => {
                productIds = products.body.data['write-product'].result.map(product => product.entities.product[0]);

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
            }).then(data => {
                languageId = data.id;

                return cy.createCustomerFixture();
            }).then((data) => {
                const reviews = productIds.map(productId => {
                    return {
                        title: 'review',
                        content: 'review content',
                        customerId: data.id,
                        productId: productId,
                        salesChannelId: salesChannelId
                    }
                })
                return cy.request({
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${authToken}`,
                        'Content-Type': 'application/json'
                    },
                    method: 'POST',
                    url: '/api/_action/sync',
                    qs: {
                        response: true
                    },
                    body: {
                        'write-product_review': {
                            'entity': 'product_review',
                            'action': 'upsert',
                            'payload': reviews
                        }

                    }
                })
            })
    });

    it('@catalogue: check that the url parameters get set', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/review/index`);

        // use the search box and check if term gets set (in the function)
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('product');

        // the sorting starts with status and createdAt, witch the URL dosn't support
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Review title',
                propertyName: 'title',
                sortDirection: 'ASC',
                location: 0
            },
            page: 1,
            limit: 25
        });

        cy.log('change Sorting direction from ASC to DESC');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');


        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Review title',
                propertyName: 'title',
                sortDirection: 'DESC',
                location: 0
            },
            page: 1,
            limit: 25
        });

        cy.log('change items per page to 10');
        cy.get('#perPage').select("10");
        cy.log('change Sorting direction from DESC to ASC')

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Review title',
                propertyName: 'title',
                sortDirection: 'DESC',
                location: 0
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
                text: 'Review title',
                propertyName: 'title',
                sortDirection: 'DESC',
                location: 0
            },
            page: 2,
            limit: 10
        });

        cy.log('change sorting to Customer')
        cy.get('.sw-data-grid__cell--3 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Customer',
                propertyName: 'customer.lastName,customer.firstName',
                sortDirection: 'ASC',
                location: 3
            },
            page: 2,
            limit: 10
        });
    });

    it('@catalogue: check that the url parameters get applied after a reload', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/review/index?term=product&page=2&limit=10&sortBy=customer.lastName,customer.firstName&sortDirection=ASC&naturalSorting=false`)

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Customer',
                propertyName: 'customer.lastName,customer.firstName',
                sortDirection: 'ASC',
                location: 3
            },
            page: 2,
            limit: 10
        });

        cy.reload();

        cy.testListing({
            searchTerm: 'product',
            sorting: {
                text: 'Customer',
                propertyName: 'customer.lastName,customer.firstName',
                sortDirection: 'ASC',
                location: 3
            },
            page: 2,
            limit: 10
        });
    });
});
