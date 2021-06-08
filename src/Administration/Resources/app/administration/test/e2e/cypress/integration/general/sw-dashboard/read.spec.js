/// <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

let product;
let storefrontCustomer;

describe('Dashboard: Test first sight of the Administration', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name'
                    }
                });
            })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                return cy.fixture('product')
            })
            .then((result) => {
                product = result;
            })
            .then(() => {
                return cy.fixture('storefront-customer')
            })
            .then((result) => {
                storefrontCustomer = result;
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @general: read dashboard', () => {
        // Check today stats
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-dashboard-index__intro-stats-today-single-stat-number-value').should('be.visible');
        cy.get('.sw-dashboard-index__intro-stats-today-single-stat-number-value').contains('1');
        cy.get('.sw-dashboard-index__intro-stats-today-single-stat-number-value').contains(product.price[0].gross);

        // check today orders
        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-data-grid__row--0').contains(`${storefrontCustomer.firstName} ${storefrontCustomer.lastName}`);

        // check if chart is visible
        cy.get('.apexcharts-canvas .apexcharts-title-text').should('be.visible');
        cy.get('.apexcharts-canvas .apexcharts-title-text').contains('Orders');
        cy.get('.apexcharts-canvas .apexcharts-title-text').contains('Turnover');

        // Check link in grid
        cy.get('.sw-data-grid__row--0 .sw-data-grid__actions-menu').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__actions-menu').click();
        cy.get('.sw-context-menu-item.sw-order-list__order-view-action').should('be.visible');
        cy.get('.sw-context-menu-item.sw-order-list__order-view-action').click();
        cy.get('.sw-order-user-card__metadata-user-name').should('be.visible');
        cy.get('.sw-order-user-card__metadata-user-name').contains(`${storefrontCustomer.firstName} ${storefrontCustomer.lastName}`);
    });
});
