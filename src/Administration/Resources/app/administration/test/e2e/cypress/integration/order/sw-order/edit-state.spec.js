// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

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

    it('@base @order: edit order state', () => {
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('orderCall');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-order-state-select__order-state .sw-loader__element').should('not.exist');
        page.setOrderState({
            stateTitle: 'Reminded',
            type: 'payment',
            signal: 'warning',
            call: 'remind'
        });

        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-order-state-select__order-state .sw-loader__element').should('not.exist');

        // Change order state to "Cancelled"
        page.setOrderState({
            stateTitle: 'Cancelled',
            type: 'order',
            signal: 'danger',
            call: 'cancel'
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`)
            .contains('Cancelled');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-order-state-select__order-state .sw-loader__element').should('not.exist');
        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral',
            call: 'reopen'
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

        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-order-state-select__order-state .sw-loader__element').should('not.exist');
        page.setOrderState({
            stateTitle: 'In progress',
            type: 'order',
            signal: 'progress',
            call: 'process'
        });
        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-order-state-select__order-state .sw-loader__element').should('not.exist');

        // Change payment state to "Paid"
        page.setOrderState({
            stateTitle: 'Paid',
            type: 'payment',
            signal: 'success',
            call: 'pay'
        });
        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-order-state-select__order-state .sw-loader__element').should('not.exist');

        // Set state to "Done"
        page.setOrderState({
            stateTitle: 'Done',
            type: 'order',
            signal: 'success',
            call: 'complete'
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--stateMachineState-name`)
            .contains('Done');
    });

    it('@order: check order history', () => {
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('orderCall');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');
        cy.get('.sw-order-delivery-metadata').scrollIntoView();

        // Check current order and payment status history
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'payment',
            signal: 'neutral',
            call: 'reopen'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Open',
            type: 'order',
            signal: 'neutral',
            call: 'reopen'
        });

        // Set order status to \"Cancelled\"': (browser) => {
        page.setOrderState({
            stateTitle: 'Cancelled',
            type: 'order',
            scope: 'history-card',
            call: 'cancel'
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
            scope: 'history-card',
            call: 'remind'
        });
        page.checkOrderHistoryEntry({
            stateTitle: 'Reminded',
            type: 'payment',
            signal: 'warning',
            position: 1
        });

        // Set order status to "Open"
        page.setOrderState({
            stateTitle: 'Open',
            type: 'order',
            scope: 'history-card',
            call: 'reopen'
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
            scope: 'history-card',
            call: 'process'
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
            scope: 'history-card',
            call: 'complete'
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
            scope: 'history-card',
            call: 'pay'
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
