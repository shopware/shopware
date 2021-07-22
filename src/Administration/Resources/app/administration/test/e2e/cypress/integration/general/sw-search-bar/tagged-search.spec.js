// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Search bar: Check search functionality withtags', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            });
    });

    it('@base @searchBar @search: search for a product using tag in dashboard', () => {
        cy.createProductFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container').should('be.visible');
            cy.contains('.sw-search-bar__type', 'Products').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type', 'Products').should('be.visible');
        });
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container--v2').should('be.visible');
            cy.contains('.sw-search-bar__type-item-name', 'Products').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Products').should('be.visible');
        });

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

    it('@searchBar @search: search for a category using tag in dashboard', () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container').should('be.visible');
            cy.contains('.sw-search-bar__type', 'Categories').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type', 'Categories').should('be.visible');
        });
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container--v2').should('be.visible');
            cy.contains('.sw-search-bar__type-item-name', 'Categories').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Categories').should('be.visible');
        });

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

    it('@searchBar @search: search for a customer using tag in dashboard', () => {
        cy.createCustomerFixture()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container').should('be.visible');
            cy.contains('.sw-search-bar__type', 'Customers').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type', 'Customers').should('be.visible');
        });
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container--v2').should('be.visible');
            cy.contains('.sw-search-bar__type-item-name', 'Customers').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Customers').should('be.visible');
        });

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

    it('@searchBar @search: search for a order using tag in dashboard', () => {
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

        cy.get('input.sw-search-bar__input').click();
        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container').should('be.visible');
            cy.contains('.sw-search-bar__type', 'Orders').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type', 'Orders').should('be.visible');
        });
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container--v2').should('be.visible');
            cy.contains('.sw-search-bar__type-item-name', 'Orders').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Orders').should('be.visible');
        });

        cy.get('input.sw-search-bar__input').type('Max Mustermann');
        cy.get('.sw-search-bar__results').should('be.visible');

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

    it('@searchBar @search: search for a media using tag in dashboard', () => {
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
        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').should('be.visible');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-dashboard')
            .should('exist');

        cy.get('.sw-loader__element')
            .should('not.exist');

        cy.get('input.sw-search-bar__input').click();
        cy.skipOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container').should('be.visible');
            cy.contains('.sw-search-bar__type', 'Media').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type', 'Media').should('be.visible');
        });
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            cy.get('.sw-search-bar__types_container--v2').should('be.visible');
            cy.contains('.sw-search-bar__type-item-name', 'Media').click();
            cy.contains('.sw-search-bar__field .sw-search-bar__type--v2', 'Media').should('be.visible');
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
});
