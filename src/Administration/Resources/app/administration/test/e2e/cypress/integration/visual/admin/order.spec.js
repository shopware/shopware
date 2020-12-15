/// <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            return cy.setShippingMethodInSalesChannel('Standard');
        }).then(() => {
            // freezes the system time to Jan 1, 2018
            const now = new Date(2018, 1, 1);
            cy.clock(now);
        })
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
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
        });
    });

    it('@visual: check appearance of basic order workflow', () => {
        const page = new OrderPageObject();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.changeElementStyling('.sw-data-grid__cell--orderDateTime', 'color: #fff');
        cy.takeSnapshot('Order listing', '.sw-order-list');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Take snapshot for visual testing
        cy.changeElementStyling('.sw-order-user-card__metadata-item', 'color: #fff');
        cy.changeElementStyling(
            '.sw-order-state-history-card__payment-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.changeElementStyling(
            '.sw-order-state-history-card__delivery-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.changeElementStyling(
            '.sw-order-state-history-card__order-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.changeElementStyling(
            '.sw-card-section--secondary > .sw-container > :nth-child(2) > :nth-child(4)',
            'color: rgb(240, 242, 245);'
        );
        cy.takeSnapshot('Order detail', '.sw-order-detail');
    });
});
