// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState().then(() => {
            return cy.setShippingMethodInSalesChannel('Standard');
        }).then(() => {
            return cy.createProductFixture();
        }).then(() => {
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
            });
    });

    beforeEach(() => {
        cy.loginViaApi().then(() => {
            // freezes the system time to Jan 1, 2018
            const now = new Date(2018, 1, 1);
            cy.clock(now);
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
        });
    });

    it.only('@visual: check appearance of basic order workflow', () => {
        const page = new OrderPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-data-grid__cell--orderNumber').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-order-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--orderDateTime', 'color: #fff');
        cy.get('.sw-data-grid__cell--orderDateTime')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Order] Listing', '.sw-order-list');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');

        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.contextMenuButton}`).click();
        cy.get('.sw-context-menu')
            .should('be.visible');

        cy.takeSnapshot('[Order] Listing, Context menu open', '.sw-page');

        cy.get('.sw-order-list__order-view-action').click();

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-order-user-card__metadata-item', 'color: #fff');
        cy.get('.sw-order-user-card__metadata-item')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-order-state-history-card__payment-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.get('.sw-order-state-history-card__payment-state .sw-order-state-card__date')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-order-state-history-card__delivery-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.get('.sw-order-state-history-card__delivery-state .sw-order-state-card__date')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-order-state-history-card__order-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.get('.sw-order-state-history-card__order-state .sw-order-state-card__date')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-card-section--secondary > .sw-container > :nth-child(2) > :nth-child(4)',
            'color: rgb(240, 242, 245);'
        );

        cy.get('.sw-card-section--secondary > .sw-container > :nth-child(2) > :nth-child(4)')
            .should('have.css', 'color', 'rgb(240, 242, 245)');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Order] Detail', '.sw-order-detail');
    });

    it('@visual: check appearance of order creation workflow', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-order-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.get('.sw-order-list').should('be.visible');
        cy.contains('.sw-button', 'Add order').click();

        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Order] Create', '.sw-order-user-card');
    });
});
