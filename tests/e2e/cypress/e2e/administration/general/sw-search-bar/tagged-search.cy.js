// / <reference types="Cypress" />

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';

describe('Search bar: Check search functionality with tags', () => {
    beforeEach(() => {
        cy.loginViaApi();
    });

    it('@base @searchBar @search: search for a product using tag in dashboard', { tags: ['pa-system-settings'] }, () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-dashboard-index__content')
            .should('be.visible');
        cy.get('input.sw-search-bar__input').type('#');
        cy.get('.sw-search-bar__types_container--v2').should('be.visible');
        cy.contains('.sw-search-bar__type-item-name', 'Products').click();
        cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Products').should('be.visible');

        cy.get('input.sw-search-bar__input').type('Product');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.contains('.sw-search-bar-item', 'Product name')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'Product name')
            .should('be.visible');
    });

    it('@searchBar @search: search for a category using tag in dashboard', { tags: ['pa-system-settings'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('input.sw-search-bar__input').type('#')
        cy.get('.sw-search-bar__types_container--v2').should('be.visible');
        cy.contains('.sw-search-bar__type-item-name', 'Categories').click();
        cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Categories').should('be.visible');

        cy.get('input.sw-search-bar__input').type('Home');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.contains('.sw-search-bar-item', 'Home')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'Home')
            .should('be.visible');
    });

    it('@searchBar @search: search for a customer using tag in dashboard', { tags: ['pa-system-settings'] }, () => {
        cy.createCustomerFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('input.sw-search-bar__input').type('#');
        cy.get('.sw-search-bar__types_container--v2').should('be.visible');
        cy.contains('.sw-search-bar__type-item-name', 'Customers').click();
        cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Customers').should('be.visible');

        cy.get('input.sw-search-bar__input').type('Pep Eroni');
        cy.get('.sw-search-bar__results').should('be.visible');
        cy.contains('.sw-search-bar-item', 'Pep Eroni')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'Pep Eroni')
            .should('be.visible');
    });

    it('@searchBar @search: search for a order using tag in dashboard', { tags: ['pa-system-settings'] }, () => {
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

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('input.sw-search-bar__input').type('#');
        cy.get('.sw-search-bar__types_container--v2').should('be.visible');
        cy.contains('.sw-search-bar__type-item-name', 'Orders').click();
        cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Orders').should('be.visible');

        cy.get('input.sw-search-bar__input').type('Max Mustermann');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.contains('.sw-search-bar-item', 'Max Mustermann 10000')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'Order 10000')
            .should('be.visible');
    });

    it('@searchBar @search: search for a media using tag in dashboard', { tags: ['pa-system-settings'] }, () => {
        cy.createDefaultFixture('media-folder')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });

        const page = new MediaPageObject();

        cy.setEntitySearchable('media', ['fileName', 'title']);

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        // Upload image in folder
        cy.contains(page.elements.smartBarHeader, 'A thing to fold about');
        page.uploadImageUsingFileUpload('img/sw-login-background.png');

        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').should('be.visible');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.get('input.sw-search-bar__input').type('#')
        cy.get('.sw-search-bar__types_container--v2').should('be.visible');
        cy.contains('.sw-search-bar__type-item-name', 'Media').click();
        cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Media').should('be.visible');

        cy.get('input.sw-search-bar__input').type('sw-login-background');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.contains('.sw-search-bar-item', 'sw-login-background')
            .should('be.visible')
            .click();

        cy.get('.sw-media-media-item')
            .should('be.visible')
            .contains('.sw-media-base-item__name', 'sw-login-background');
    });
});
