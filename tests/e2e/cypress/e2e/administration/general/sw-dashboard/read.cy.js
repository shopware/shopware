// / <reference types="Cypress" />

let product;
let storefrontCustomer;

describe('Dashboard: Test first sight of the Administration', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.searchViaAdminApi({
                endpoint: 'product',
                data: {
                    field: 'name',
                    value: 'Product name',
                },
            });
        })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                return cy.fixture('product');
            })
            .then((result) => {
                product = result;
            })
            .then(() => {
                return cy.fixture('storefront-customer');
            })
            .then((result) => {
                storefrontCustomer = result;
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @general: read dashboard', { tags: ['pa-merchant-services'] }, () => {
        // Check today stats
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');

        cy.get('.sw-dashboard-statistics__intro-stats-today-single-stat-number-value').should('be.visible');
        cy.contains('.sw-dashboard-statistics__intro-stats-today-single-stat-number-value', '1');
        cy.contains('.sw-dashboard-statistics__intro-stats-today-single-stat-number-value', product.price[0].gross);

        // check today orders
        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.contains('.sw-data-grid__row--0', `${storefrontCustomer.firstName} ${storefrontCustomer.lastName}`);

        cy.get('.sw-dashboard-statistics .sw-card__title').each((item) => {
            cy.wrap(item).contains(/Orders|Turnover/g);
        });
        cy.get('.apexcharts-canvas').should('be.visible');

        // Check link in grid
        cy.get('.sw-data-grid__row--0 .sw-data-grid__actions-menu').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__actions-menu').click();
        cy.get('.sw-context-menu-item.sw-order-list__order-view-action').should('be.visible');
        cy.get('.sw-context-menu-item.sw-order-list__order-view-action').click();
    });
});
