// / <reference types="Cypress" />
import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Flow builder: set order status testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            }).then(() => {
                return cy.createProductFixture();
            }).then(() => {
                return cy.createCustomerFixture();
            });
    });

    it('@settings: set order state flow', () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST'
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New flow');

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
            .typeSingleSelect('Set status', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-set-order-state-modal').should('be.visible');

        cy.get('#sw-field--config-order').select('In progress')
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
        cy.get('.btn-buy').contains('Add to shopping ').click();
        cy.get('.offcanvas').should('be.visible');
        cy.get('.cart-item-price').contains('64');

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);

        // Finish checkout
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-ordernumber').contains('Your order number: #10000');

        // Clear Storefront cookie
        cy.clearCookies();

        const page = new OrderPageObject();

        cy.loginViaApi().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-data-grid-skeleton').should('not.exist');
            cy.get(`${page.elements.dataGridRow}--0`).contains('10000');
        });

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-state-select__order-state').contains('Order status: In progress');
            cy.get('.sw-order-state-select__payment-state').contains('Payment status: In Progress');
            cy.get('.sw-order-state-select__delivery-state').contains('Delivery status: Shipped');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-state-select-v2 .sw-single-select[label="Order status"]').contains('In progress');
            cy.get('.sw-order-state-select-v2 .sw-single-select[label="Payment status"]').contains('In Progress');
            cy.get('.sw-order-state-select-v2 .sw-single-select[label="Delivery status"]').contains('Shipped');
        });
    });
});
