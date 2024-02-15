// / <reference types="Cypress" />
import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Flow builder: Add remove tag testing', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        });
    });

    it('@settings: add and remove tag action flow', { tags: ['pa-services-settings'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'post',
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v2');
        cy.get('#sw-field--flow-priority').type('12');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('checkout order placed');
        cy.get('.sw-flow-trigger__input-field').type('{enter}');

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();

        // Open Add tag modal
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Add tag', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        // Agg tag "New Customer" for Order
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('New Customer');
        cy.contains('.sw-select-result-list-popover-wrapper', 'Add "New Customer"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.contains('.sw-select-result-list-popover-wrapper', 'New Customer');
        cy.get('.sw-flow-tag-modal__tags-field input').type('{esc}');

        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        // Add new root sequence
        cy.get('.sw-flow-detail-flow__position-plus').click();

        // Select "Customers from USA" rule
        cy.get('.sw-flow-sequence-selector__add-condition').scrollIntoView().click();
        cy.get('.sw-flow-sequence-condition__selection-rule')
            .typeSingleSelect('Customers from USA', '.sw-flow-sequence-condition__selection-rule');

        cy.get('.sw-card-view__content').scrollTo('bottom');

        // Add "Not USA Customer" tag in False block
        cy.get('.sw-flow-sequence__false-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence__false-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Add tag', '.sw-flow-sequence__false-block .sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('Not USA Customer');
        cy.contains('.sw-select-result-list-popover-wrapper', 'Add "Not USA Customer"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.contains('.sw-select-result-list-popover-wrapper', 'Not USA Customer');
        cy.get('.sw-flow-tag-modal__tags-field input').type('{esc}');
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        // Remove "New Customer" tag in True block
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Remove tag', '.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field').typeMultiSelectAndCheck('New Customer');
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        // Add "USA Customer" tag in True block
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Add tag', '.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('USA Customer');
        cy.contains('.sw-select-result-list-popover-wrapper', 'Add "USA Customer"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.contains('.sw-select-result-list-popover-wrapper', 'USA Customer');
        cy.get('.sw-flow-tag-modal__tags-field input').type('{esc}');
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

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

        // Change billing address country to USA
        cy.visit('/account/address');
        cy.get('.address-list .address-card').eq(1).get('.col-auto').contains('Edit')
            .click();
        cy.get('#addressAddressCountry').select('United States of America');
        cy.contains('.address-form-submit', 'Save address').click();

        cy.get('.address-action-set-default-billing').click();

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
        cy.contains('.finish-ordernumber', 'Your order number: #10001');

        // Clear Storefront cookie
        cy.clearCookies();

        const page = new OrderPageObject();

        cy.authenticate().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.contains(`${page.elements.dataGridRow}--0`, '10001');
            cy.contains(`${page.elements.dataGridRow}--1`, '10000');
        });

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.tabs.general.summaryTagSelect} .sw-select-selection-list__item-holder`).should('have.length', 1);
        cy.contains(page.elements.tabs.general.summaryTagSelect , 'USA Customer');

        cy.get('.smart-bar__back-btn').click();
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--1`,
        );

        cy.get('.sw-loader').should('not.exist');
        cy.get(`${page.elements.tabs.general.summaryTagSelect} .sw-select-selection-list__item-holder`).should('have.length', 2);
        cy.contains(page.elements.tabs.general.summaryTagSelect, 'New Customer');
        cy.contains(page.elements.tabs.general.summaryTagSelect, 'Not USA Customer');
    });
});
