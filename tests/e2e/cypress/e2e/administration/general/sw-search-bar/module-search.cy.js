/// <reference types="Cypress" />

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';

describe('Search bar: Check search module with short keyword', () => {
    beforeEach(() => {
        cy.loginViaApi();
    });

    it('@base @searchBar @search: Search for a product using the keyword pro', { tags: ['pa-system-settings'] }, () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.get('input.sw-search-bar__input').type('Pro');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Products');

        cy.contains('.sw-search-bar-item__link[href="#/sw/product/create"]', 'Add new product')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'New product')
            .should('be.visible');
    });

    it('@searchBar @search: Search for a category using the keyword cat', { tags: ['pa-system-settings'] }, () => {
        cy.createCategoryFixture({ name: 'Sub Category'})
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            })

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.get('input.sw-search-bar__input').type('Cat');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Categories');

        cy.contains('.sw-search-bar-item', 'Add new landing page')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'Categories')
            .should('be.visible');
    });

    it('@searchBar @search: Search for a customer using the keyword cus', { tags: ['pa-system-settings'] }, () => {
        cy.createCustomerFixture({ lastName: 'customer' })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.get('input.sw-search-bar__input').type('Cus');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Customers');

        cy.contains('.sw-search-bar-item__link[href="#/sw/customer/create"]', 'Add new customer')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'New Customer')
            .should('be.visible');
    });

    it('@searchBar @search: Search for a order using the keyword ord', { tags: ['pa-system-settings'] }, () => {
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

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.get('input.sw-search-bar__input').type('Ord');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Orders');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Add new order').click();

        cy.get('.sw-order-create-initial-modal').should('be.visible');
    });

    it('@searchBar @search: Search for a media using the keyword med', { tags: ['pa-system-settings'] }, () => {
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
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Upload image in folder
        cy.contains(page.elements.smartBarHeader, 'A thing to fold about');
        page.uploadImageUsingFileUpload('img/sw-login-background.png');

        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').should('be.visible');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('.sw-dashboard-index__content')
            .should('be.visible');

        cy.get('input.sw-search-bar__input').click();
        cy.get('input.sw-search-bar__input').type('Med');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Media').click();

        cy.contains('.smart-bar__header h2', 'Media')
            .should('be.visible');
    });

    it('@searchBar @search: Search for a product using the keyword add new prod', { tags: ['pa-system-settings'] }, () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.get('input.sw-search-bar__input').type('add new prod');
        cy.get('.sw-search-bar__results').should('be.visible');

        cy.get('.sw-search-bar-item')
            .should('be.visible');

        cy.contains('.sw-search-bar-item', 'Add new product');

        cy.contains('.sw-search-bar-item__link[href="#/sw/product/create"]', 'Add new product')
            .should('be.visible')
            .click();

        cy.contains('.smart-bar__header h2', 'New product')
            .should('be.visible');
    });
});
