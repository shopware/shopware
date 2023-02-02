// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Visual tests', () => {
    beforeEach(() => {
        cy.setShippingMethodInSalesChannel('Standard').then(() => {
            return cy.createProductFixture();
        }).then(() => {
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
            }).then(() => {
            // freezes the system time to Jan 1, 2018
                const now = new Date(2018, 1, 1);
                cy.clock(now, ['Date']);
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic order workflow', { tags: ['pa-customers-orders'] }, () => {
        const page = new OrderPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-data-grid__cell--orderNumber').should('be.visible');
        cy.get('.navigation-list-item__type-plugin').should('exist');
        cy.get('.navigation-list-item__type-plugin').should('have.length', 3);
        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index',
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-order-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--orderDateTime', 'color: #fff');
        cy.get('.sw-data-grid__cell--orderDateTime')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Listing', '.sw-order-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');

        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.contextMenuButton}`).click();
        cy.get('.sw-context-menu')
            .should('be.visible');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Listing, Context menu open', '.sw-page', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-order-list__order-view-action').click();

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Detail', '.sw-order-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });

    it('@visual: check appearance of order creation workflow', { tags: ['pa-customers-orders'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('getData');

        cy.get('.navigation-list-item__type-plugin').should('exist');
        cy.get('.navigation-list-item__type-plugin').should('have.length', 3);
        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index',
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-order-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.get('.sw-order-list').should('be.visible');
        cy.contains('.sw-button', 'Add order').click();

        cy.get('.sw-loader').should('not.exist');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Create', '.sw-order-create', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
