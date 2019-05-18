/// <reference types="Cypress" />

import OrderPageObject from '../../support/pages/module/sw-order.page-object';

describe('Order: Test order state', () => {
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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
            });
    });

    it('@p edit order state', () => {
        const page = new OrderPageObject();

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader__element').should('not.exist');

        page.setOrderState({
            stateTitle: 'Reminded',
            type: 'payment',
            signal: 'progress'
        });
        // Change order state to "Cancelled"

        page.setOrderState({
            stateTitle: 'Cancelled',
            type: 'order',
            signal: 'danger'
        });
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`)
            .contains('Cancelled');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral'
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`)
            .contains('Open');

        // Change order state to "In progress" then "Complete"
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        page.setOrderState({
            stateTitle: 'In progress',
            type: 'order',
            signal: 'progress'
        });

        // Change payment state to "Paid"
        page.setOrderState({
            stateTitle: 'Paid',
            type: 'payment',
            signal: 'success'
        });

        // Set state to "Done"
        page.setOrderState({
            stateTitle: 'Done',
            type: 'order',
            signal: 'success'
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`)
            .contains('Done');
    });

    it('check order history', () => {
        const page = new OrderPageObject();

        cy.get(`${page.elements.dataGridRow}--0`).contains('Max Mustermann');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');
        cy.get('.sw-order-delivery-metadata').scrollIntoView();

        // Check current order and payment status history
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'payment',
            signal: 'neutral'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral'
        });

        // Set order status to \"Cancelled\"': (browser) => {
        page.setOrderState({
            stateTitle: 'Cancelled',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Cancelled',
            type: 'order',
            signal: 'danger',
            position: 1
        });

        // Set payment status to "Reminded"
        page.setOrderState({
            stateTitle: 'Reminded',
            type: 'payment',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Reminded',
            type: 'payment',
            signal: 'progress',
            position: 1
        });

        // Set order status to "Open"
        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'order',
            position: 2
        });

        // Set order status to "In progess"
        page.setOrderState({
            stateTitle: 'In progress',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'In progress',
            type: 'order',
            signal: 'progress',
            position: 3
        });

        // Set order status to "Done"
        page.setOrderState({
            stateTitle: 'Done',
            type: 'order',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Done',
            type: 'order',
            signal: 'success',
            position: 4
        });

        // Set payment status to "Paid"
        page.setOrderState({
            stateTitle: 'Paid',
            type: 'payment',
            scope: 'history-card'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Paid',
            type: 'payment',
            signal: 'success',
            position: 2
        });

        // Verify order completion in listing
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`)
            .contains('Done');
    });
});
