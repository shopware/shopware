// / <reference types="Cypress" />
import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Flow builder: set order status testing', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        });
    });

    it('@settings: set order state flow', { tags: ['pa-services-settings'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Assign status', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-set-order-state-modal').should('be.visible');

        cy.get('#sw-field--config-order').select('In Progress')
            .should('have.value', 'in_progress');

        cy.get('#sw-field--config-order_transaction').select('In Progress')
            .should('have.value', 'in_progress');

        cy.get('#sw-field--config-order_delivery').select('Shipped')
            .should('have.value', 'shipped');

        cy.get('.sw-flow-set-order-state-modal__save-button').click();
        cy.get('.sw-flow-set-order-state-modal').should('not.exist');

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        cy.visit('/');

        cy.contains('.btn-buy', 'Add to shopping ').click();
        cy.get('.offcanvas').should('be.visible');

        cy.contains('.line-item-price', '49.98');

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();
        cy.get('.checkout-confirm-tos-label').click(1, 1);

        // Finish checkout
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.contains('.finish-ordernumber', 'Your order number: #10000');

        // Clear Storefront cookie
        cy.clearCookies();

        const page = new OrderPageObject();

        cy.authenticate().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.contains(`${page.elements.dataGridRow}--0`, '10000');
        });

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-order-state-select-v2 .sw-single-select[label="Order status"]', 'In Progress');
        cy.contains('.sw-order-state-select-v2 .sw-single-select[label="Payment status"]', 'In Progress');
        cy.contains('.sw-order-state-select-v2 .sw-single-select[label="Delivery status"]', 'Shipped');
    });
    it('@settings: set order state flow with force transition', { tags: ['pa-services-settings'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Assign status', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-set-order-state-modal').should('be.visible');
        cy.get('#sw-field--config-order').should('be.visible');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('#sw-field--config-order').select('Done')
            .should('have.value', 'completed');

        cy.get('#sw-field--config-order_transaction').select('Refunded')
            .should('have.value', 'refunded');

        cy.get('#sw-field--config-order_delivery').select('Returned')
            .should('have.value', 'returned');

        cy.get('.sw-flow-set-order-state-modal__force-transition').click();

        cy.get('.sw-flow-set-order-state-modal__save-button').click();
        cy.get('.sw-flow-set-order-state-modal').should('not.exist');

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        cy.visit('/');
        cy.contains('.btn-buy', 'Add to shopping ').click();
        cy.get('.offcanvas').should('be.visible');

        cy.contains('.line-item-price', '49.98');

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();
        cy.get('.checkout-confirm-tos-label').click(1, 1);

        // Finish checkout
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.contains('.finish-ordernumber', 'Your order number: #10000');

        // Clear Storefront cookie
        cy.clearCookies();

        const page = new OrderPageObject();

        cy.authenticate().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.contains(`${page.elements.dataGridRow}--0`, '10000');
        });

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-order-state-select-v2 .sw-single-select[label="Order status"]', 'Done');
        cy.contains('.sw-order-state-select-v2 .sw-single-select[label="Payment status"]', 'Refunded');
        cy.contains('.sw-order-state-select-v2 .sw-single-select[label="Delivery status"]', 'Returned');
    });
});
