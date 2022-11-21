/// <reference types="Cypress" />
import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Bulk edit orders', () => {
    beforeEach(() => {
        cy.loginViaApi()
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
            .then((product) => {
                return cy.createGuestOrder(product.id);
            })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@package @order: should modify orders with the bulk edit functionality', { tags: ['pa-system-settings'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
        }).as('saveOrder');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST'
        }).as('getUserConfig');

        const page = new OrderPageObject();

        // Create the second order
        cy.contains('Add order').click();
        cy.contains('h2', 'New order');
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('be.disabled');
        cy.get('.sw-order-create-details-header .sw-entity-single-select')
            .typeSingleSelectAndCheck('Eroni', '.sw-order-create-details-header .sw-entity-single-select');
        cy.get('.sw-order-create-details-body input[name="sw-field--email"]')
            .should('have.value', 'test@example.com');
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('not.be.disabled');
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group').click();

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`).dblclick();
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');
        cy.get(`${page.elements.dataGridColumn}--quantity input`)
            .clearTypeAndCheck('10');
        cy.get(`${page.elements.dataGridInlineEditSave}`).click();
        cy.wait('@addLineItem').its('response.statusCode').should('equal', 200);

        // Save order
        cy.contains('Save order').click();
        cy.get('.sw-order-create__remind-payment-modal-decline').click();
        cy.wait('@saveOrder').its('response.statusCode').should('equal', 200);

        // Bulk edit
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'order/index');
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.link.link-primary').click();
        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('#modalTitleEl').should('be.visible');
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.url().should('include', 'bulk/edit/order');
        cy.get('.smart-bar__header').contains('Bulk edit: 2 orders');

        // Make changes on both orders (Payment status, Delivery status, Order status)
        cy.get('.sw-bulk-edit-change-field-orderTransactions [type="checkbox"]').click();
        cy.get('[name="orderTransactions"] .sw-block-field__block')
            .typeSingleSelectAndCheck('Authorized', '[name="orderTransactions"] .sw-block-field__block');
        cy.get('.sw-bulk-edit-change-field-orderDeliveries [type="checkbox"]').click();
        cy.get('[name="orderDeliveries"] .sw-block-field__block')
            .typeSingleSelectAndCheck('Shipped (partially)', '[name="orderDeliveries"] .sw-block-field__block');
        cy.get('.sw-bulk-edit-change-field-orders [type="checkbox"]').click();
        cy.get('[name="orders"] .sw-block-field__block')
            .typeSingleSelectAndCheck('Cancelled', '[name="orders"] .sw-block-field__block');

        // Apply the changes
        cy.get('.sw-bulk-edit-order__save-action.sw-button-process').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.footer-right .sw-button--primary', 'Apply changes').click();

        cy.get('.sw-loader').should('not.exist');
        cy.contains('Bulk edit finished').should('exist');

        cy.contains('.footer-right .sw-button', 'Close').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');

        // Verify changes from the first order
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'order/index');
        cy.get('.sw-data-grid__row.sw-data-grid__row--0')
            .within(() => {
                cy.contains('Authorized').should('exist');
                cy.contains('Shipped (partially)').should('exist');
                cy.contains('Cancelled').should('exist');
            });

        // Verify changes from the second order
        cy.get('.sw-data-grid__row.sw-data-grid__row--1')
            .within(() => {
                cy.contains('Authorized').should('exist');
                cy.contains('Shipped (partially)').should('exist');
                cy.contains('Cancelled').should('exist');
            });
    });

    it('@package @order: should be able to generate documents with the bulk edit functionality', { tags: ['pa-system-settings'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST'
        }).as('getUserConfig');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/document/**/create`,
            method: 'POST'
        }).as('createDocument');

        // Bulk edit
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.url().should('include', 'order/index');
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.link.link-primary').click();
        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('#modalTitleEl').should('be.visible');
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.url().should('include', 'bulk/edit/order');
        cy.get('.smart-bar__header').contains('Bulk edit: 1 order');

        // Choose generating invoice document
        cy.get('.sw-bulk-edit-change-field-invoice [type="checkbox"]')
            .click();
        cy.get('.sw-bulk-edit-change-field-invoice textarea[name=sw-field--generateData-documentComment]')
            .type('Invoice generated via bulk edit');

        // Apply the changes
        cy.get('.sw-bulk-edit-order__save-action.sw-button-process').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.footer-right .sw-button--primary').contains('Apply changes');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal-process').should('exist');
        cy.get('.sw-bulk-edit-save-modal-process__generate-document.is--invoice').should('exist');
        cy.wait('@createDocument').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.sw-modal__header > .sw-modal__close').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');

        // Verify changes from the order
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.url().should('include', 'order/index');
        cy.get('.sw-data-grid__row.sw-data-grid__row--0').find('.sw-context-button__button').click();
        cy.contains('.sw-context-menu-item', 'View').click();
        cy.get('.sw-order-document-card .sw-data-grid__row.sw-data-grid__row--0').contains('Invoice');
    });

    it('@package @order: should be able to download documents with the bulk edit functionality', { tags: ['pa-system-settings'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST'
        }).as('getUserConfig');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/document/download`,
            method: 'POST'
        }).as('downloadDocument');

        // Bulk edit
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.url().should('include', 'order/index');
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.link.link-primary').click();
        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('#modalTitleEl').should('be.visible');
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.url().should('include', 'bulk/edit/order');
        cy.get('.smart-bar__header').contains('Bulk edit: 1 order');

        // Choose generating invoice document
        cy.get('.sw-bulk-edit-change-field-invoice [type="checkbox"]')
            .click();
        cy.get('.sw-bulk-edit-change-field-invoice textarea[name=sw-field--generateData-documentComment]')
            .type('Invoice generated via bulk edit');

        // Choose downloading invoice document
        cy.get('.sw-bulk-edit-change-field-download .sw-bulk-edit-change-field-renderer__change-field [type="checkbox"]')
            .click();
        cy.get('.sw-bulk-edit-order-documents-download-documents__checkbox.is--invoice [type="checkbox"]')
            .click();

        // Apply the changes
        cy.get('.sw-bulk-edit-order__save-action.sw-button-process').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.footer-right .sw-button--primary').contains('Apply changes');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.sw-bulk-edit-save-modal-success__download-document.is--invoice').should('exist');
        cy.get('.sw-bulk-edit-save-modal-success__download-document.is--invoice .action').click();
        cy.wait('@downloadDocument').its('response.statusCode').should('equal', 200);
        cy.get('.footer-right > .sw-button').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');
    });
});
