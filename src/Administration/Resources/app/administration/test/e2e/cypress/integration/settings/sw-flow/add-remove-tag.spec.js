// / <reference types="Cypress" />
import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Flow builder: Add remove tag testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
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

    it('@settings: add and remove tag action flow', () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'post'
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New flow');

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
        cy.get('.sw-select-result-list-popover-wrapper').contains('Add "New Customer"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list-popover-wrapper').contains('New Customer');
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
            .typeSingleSelect('Add tag', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('Not USA Customer');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Add "Not USA Customer"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Not USA Customer');
        cy.get('.sw-flow-tag-modal__tags-field input').type('{esc}');
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        // Remove "New Customer" tag in True block
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Remove tag', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field').typeMultiSelectAndCheck('New Customer');
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        // Add "USA Customer" tag in True block
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__add-button').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Add tag', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('USA Customer');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Add "USA Customer"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list-popover-wrapper').contains('USA Customer');
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

        // Change billing address country to USA
        cy.visit('/account/address');
        cy.get('.address-list .address-card').eq(1).get('.col-auto').contains('Edit')
            .click();
        cy.get('#addressAddressCountry').select('USA');
        cy.get('.address-form-submit').contains('Save address').click();

        cy.get('.address-action-set-default-billing').click();

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
        cy.get('.finish-ordernumber').contains('Your order number: #10001');

        // Clear Storefront cookie
        cy.clearCookies();

        const page = new OrderPageObject();

        cy.loginViaApi().then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-data-grid-skeleton').should('not.exist');
            cy.get(`${page.elements.dataGridRow}--0`).contains('10001');
            cy.get(`${page.elements.dataGridRow}--1`).contains('10000');
        });

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-user-card__tag-select .sw-select-selection-list__item-holder').should('have.length', 1);
            cy.get('.sw-order-user-card__tag-select').contains('USA Customer');

            cy.get('.smart-bar__back-btn').click();
            cy.get('.sw-data-grid-skeleton').should('not.exist');

            cy.clickContextMenuItem(
                '.sw-order-list__order-view-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--1`
            );

            cy.get('.sw-loader').should('not.exist');
            cy.get('.sw-order-user-card__tag-select .sw-select-selection-list__item-holder').should('have.length', 2);
            cy.get('.sw-order-user-card__tag-select').contains('New Customer');
            cy.get('.sw-order-user-card__tag-select').contains('Not USA Customer');
        });


        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-detail-base__general-info__order-tags .sw-select-selection-list__item-holder').should('have.length', 1);
            cy.get('.sw-order-detail-base__general-info__order-tags').contains('USA Customer');

            cy.get('.smart-bar__back-btn').click();
            cy.get('.sw-data-grid-skeleton').should('not.exist');

            cy.clickContextMenuItem(
                '.sw-order-list__order-view-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--1`
            );

            cy.get('.sw-loader').should('not.exist');
            cy.get('.sw-order-detail-base__general-info__order-tags .sw-select-selection-list__item-holder').should('have.length', 2);
            cy.get('.sw-order-detail-base__general-info__order-tags').contains('New Customer');
            cy.get('.sw-order-detail-base__general-info__order-tags').contains('Not USA Customer');
        });
    });
});
