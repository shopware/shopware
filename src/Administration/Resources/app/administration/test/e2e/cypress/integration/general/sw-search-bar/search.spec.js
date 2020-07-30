/// <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Search bar: Check main functionality', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            });
    });

    it('@searchBar @search: search for a product', () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').type('Product');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('Product name')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Product name');
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

        cy.get('input.sw-search-bar__input').type('Max Mustermann');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.get('.sw-search-bar__results-column > :nth-child(1)')
            .should('be.visible')
            .get('.sw-search-bar__types-header-entity')
            .contains('Order');

        cy.get('.sw-search-bar-item')
            .should('be.visible')
            .contains('10000 - Max Mustermann')
            .click();

        cy.get('.smart-bar__header h2')
            .should('be.visible')
            .contains('Order 10000');
    });

    it('@searchBar @search: search for a media', { browser: "!firefox" }, () => {
        cy.createDefaultFixture('media-folder')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });

        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );

        // Upload image in folder
        cy.get(page.elements.smartBarHeader).contains('A thing to fold about');
        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').should('be.visible');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

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
});
