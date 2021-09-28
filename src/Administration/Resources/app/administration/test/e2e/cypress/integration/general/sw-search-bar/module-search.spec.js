/// <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Search bar: Check search module with short keyword', () => {
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_6040');
    });

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            });
    });

    it('@base @searchBar @search: Search for a product using the keyword pro', () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Pro');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Products');

        cy.get('.sw-search-bar-item__link[href="#/sw/product/create"]')
            .should('be.visible')
            .contains('Add new product')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('New product');
    });

    it('@searchBar @search: Search for a category using the keyword cat', () => {
        cy.createCategoryFixture({ name: 'Sub Category'})
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            })

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Cat');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Categories');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Add new landing page')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Categories');
    });

    it('@searchBar @search: Search for a customer using the keyword cus', () => {
        cy.createCustomerFixture({ lastName: 'customer' })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Cus');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Customers');

        cy.get('.sw-search-bar-item__link[href="#/sw/customer/create"]')
            .should('be.visible')
            .contains('Add new customer')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('New Customer');
    });

    it('@searchBar @search: Search for a order using the keyword ord', () => {
        cy.createProductFixture()
            .then(() => {
                return cy.createProductFixture({
                    name: 'Awesome order',
                    productNumber: 'RS-1337',
                    description: 'l33t',
                    "price": [
                        {
                            "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                            "net": 24,
                            "linked": false,
                            "gross": 128
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
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Ord');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Orders');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Add new order')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('New order');
    });

    it('@searchBar @search: Search for a media using the keyword med', () => {
        cy.createDefaultFixture('media-folder')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });

        const page = new MediaPageObject();

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

        cy.get('input.sw-search-bar__input').type('Med');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Media')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Media');
    });
});
