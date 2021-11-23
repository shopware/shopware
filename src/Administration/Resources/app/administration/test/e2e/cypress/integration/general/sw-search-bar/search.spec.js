// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

const uuid = require('uuid/v4');

function setMediaEntitySearchable() {
    cy.window().then(($w) => {
        const search = $w.Shopware.Module.getModuleByEntityName('media')
            .manifest.defaultSearchConfiguration;
        search._searchable = true;
        search.fileName._searchable = true;
        search.title._searchable = true;
    });
}

describe('Search bar: Check main functionality', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            });
    });

    it('@base @searchBar @search: search for a product', () => {
        let taxId; let
            currencyId;

        cy.createDefaultFixture('tax')
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
            }).then(currency => {
                currencyId = currency.id;

                cy.authenticate();
            })
            .then(auth => {
                const products = [];
                for (let i = 1; i <= 11; i++) {
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
                            entity: 'product',
                            action: 'upsert',
                            payload: products
                        }

                    }
                });
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('product-');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-more-results__link').contains('Show all 11 matching results in products...');
        });
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('product-')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('product-');
    });

    it('@searchBar @search: search for a category', () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Home');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Home')
            .click();
        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Home');
    });

    it('@searchBar @search: search for a customer', () => {
        cy.createCustomerFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Pep Eroni');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Pep Eroni')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Pep Eroni');
    });

    it('@searchBar @search: search for a order', () => {
        cy.createProductFixture()
            .then(() => {
                return cy.createProductFixture({
                    name: 'Awesome product',
                    productNumber: 'RS-1337',
                    description: 'l33t',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            net: 24,
                            linked: false,
                            gross: 128
                        }
                    ]
                });
            }).then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name'
                    }
                });
            }).then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Max Mustermann');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar__results-column > :nth-child(1)')
            .should('be.visible')
            .get('.sw-search-bar__types-header-entity')
            .contains('Order');

        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar-item')
                .should('be.visible')
                .contains('10000 - Max Mustermann')
                .click();
        });

        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar-item')
                .should('be.visible')
                .contains('Max Mustermann 10000')
                .click();
        });

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Order 10000');
    });

    it('@searchBar @search: search for a media', () => {
        cy.createDefaultFixture('media-folder')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });

        const page = new MediaPageObject();

        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.setEntitySearchable('media', ['fileName', 'title']);
        });

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        // Upload image in folder
        cy.get(page.elements.smartBarHeader).contains('A thing to fold about');
        page.uploadImageUsingFileUpload('img/sw-login-background.png');

        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').should('be.visible');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            setMediaEntitySearchable();
        });

        cy.get('input.sw-search-bar__input').type('sw-login-background');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('sw-login-background')
            .click();

        cy.get('.sw-media-media-item')
            .should('be.visible')
            .get('.sw-media-base-item__name')
            .contains('sw-login-background');
    });

    it('@searchBar @search: toggle result box with results for the letter "e"', () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('e');
        cy.get('.sw-search-bar__results').should('be.visible');

        // navigate down to test if active item also stays the same after refocus
        cy.get('input.sw-search-bar__input').type('{downarrow}');

        // capture dom of search result box
        let searchResultsMarkup;

        // eslint-disable-next-line no-return-assign
        cy.get('.sw-search-bar__results').then($el => searchResultsMarkup = $el.html());

        cy.get('input.sw-search-bar__input').blur();
        cy.get('input.sw-search-bar__input').focus();

        // compare result box dom after refocus wit the string captured before
        cy.get('.sw-search-bar__results').then($el => expect($el.html()).to.be.equal(searchResultsMarkup));
    });

    it('@searchBar @search: navigate in the results for the letter "e"', () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('input.sw-search-bar__input').type('e');
        });

        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('input.sw-search-bar__input').type('name');
        });

        cy.get('.sw-search-bar__results').should('be.visible');

        // 'Cursor' is at the first element and should therefore not move
        cy.get('.is--active.sw-search-bar-item').invoke('text').then((resultTextBefore) => {
            // to ensure this try to move it anyways
            cy.get('input.sw-search-bar__input').type('{leftarrow}');
            cy.get('input.sw-search-bar__input').type('{uparrow}');
            cy.get('.is--active.sw-search-bar-item').invoke('text').should((resultTextAfter) => {
                expect(resultTextBefore).to.equal(resultTextAfter);
            });
        });

        // move the 'Cursor' down and then up again
        cy.get('.is--active.sw-search-bar-item').invoke('text').then((resultTextBefore) => {
            // to ensure this try to move it anyways
            cy.get('input.sw-search-bar__input').type('{downarrow}');
            cy.get('input.sw-search-bar__input').type('{uparrow}');
            cy.get('.is--active.sw-search-bar-item').invoke('text').should((resultTextAfter) => {
                expect(resultTextBefore).to.equal(resultTextAfter);
            });
        });

        // move the 'Cursor' right and then left again
        cy.get('.is--active.sw-search-bar-item').invoke('text').then((resultTextBefore) => {
            // to ensure this try to move it anyways
            cy.get('input.sw-search-bar__input').type('{rightarrow}');
            cy.get('input.sw-search-bar__input').type('{leftarrow}');
            cy.get('.is--active.sw-search-bar-item').invoke('text').should((resultTextAfter) => {
                expect(resultTextBefore).to.equal(resultTextAfter);
            });
        });

        cy.get('.sw-search-bar__results').find('.sw-search-bar-item').its('length').then((numberOfResults) => {
            // navigate to the last result based on the numberOfResults

            // eslint-disable-next-line no-plusplus
            for (let i = 1; i <= numberOfResults; i++) {
                cy.get('input.sw-search-bar__input').type('{downarrow}');
            }

            // 'Cursor' is at the last element and should therefore not move
            cy.get('.is--active.sw-search-bar-item').invoke('text').then((resultTextBefore) => {
                // to ensure this try to move it anyways
                cy.get('input.sw-search-bar__input').type('{downarrow}');
                cy.get('input.sw-search-bar__input').type('{rightarrow}');
                cy.get('.is--active.sw-search-bar-item').invoke('text').should((resultTextAfter) => {
                    expect(resultTextBefore).to.equal(resultTextAfter);
                });
            });
        });
    });
});
