// / <reference types="Cypress" />
import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Create order', () => {
    beforeEach(() => {
        cy.createCustomerFixture().then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @order create new order', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST',
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart`,
            method: 'GET',
        }).as('getCart');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST',
        }).as('saveOrder');

        // navigate to order create page
        cy.contains('Add order').click();

        cy.get('.sw-order-create-initial-modal').should('be.visible');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 input').check();

        cy.get('.sw-order-customer-grid__sales-channel-selection-modal').should('be.visible');
        cy.get('.sw-order-customer-grid__sales-channel-selection-modal .sw-button--primary').click();

        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-order-create-initial-modal__tab-product').should('not.be.disabled');
        cy.get('.sw-order-create-initial-modal__tab-product').click();

        // continue adding a valid line item
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group')
            .click();

        // expect a new table row visible
        cy.get(`${page.elements.dataGridRow}--0`)
            .should('be.visible');

        // double click on item name cell
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`)
            .dblclick();

        // enter item name
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        // enter item quantity
        cy.get(`${page.elements.dataGridColumn}--quantity input`)
            .clearTypeAndCheck('10');

        // save line item
        cy.get(`${page.elements.dataGridInlineEditSave}`)
            .click();

        cy.wait('@addLineItem').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button--primary').click();

        cy.get('.sw-order-create-initial-modal').should('not.be.exist');

        cy.wait('@getCart').its('response.statusCode').should('equal', 200);

        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--quantity`, '10');

        cy.contains(`.sw-order-create-general-info__summary-main-header`, 'Pep Eroni');
        cy.contains('.sw-order-create-summary__data', '€499.80');

        // save order
        cy.contains('Save order')
            .click();

        // deny payment reminder
        cy.get('.sw-order-create__remind-payment-modal-decline')
            .click();

        cy.wait('@saveOrder').its('response.statusCode').should('equal', 200);

        // assert saving successful
        cy.get('.sw-order-detail').should('be.visible');

        cy.contains(page.elements.tabs.general.summaryMainHeader, 'Pep Eroni');
        cy.contains('.sw-order-detail__summary', '€499.80');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--quantity`, '10');
    });

    // TODO: needs to be fixed for sw-promotion-v2-discounts
    it.skip('@base @order: add promotion code', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        cy.visit(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.smart-bar__header', 'Promotions');

        cy.get('.smart-bar__actions .sw-button--primary').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/promotion/**/discounts`,
            method: 'POST',
        }).as('saveDiscount');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'PATCH',
        }).as('patchPromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST',
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST',
        }).as('saveOrder');

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck('New year promotion');
        cy.get('input[name="sw-field--promotion-active"]').click();

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@savePromotion')
            .its('response.statusCode').should('equal', 204);

        cy.get('#sw-field--selectedCodeType').select('Fixed promotion code');
        cy.get('#sw-field--promotion-code').typeAndCheck('DISCOUNT');

        // Add to Storefront SalesChannel
        cy.get(page.elements.loader).should('not.exist');
        cy.get('a[title="Conditions"]').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-v2-detail__save-action').click();

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--name`, 'New year promotion');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--name a`)
            .click();

        // Add discount
        cy.get(page.elements.loader).should('not.exist');
        cy.get('a[title="Discounts"]').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-button--ghost').should('be.visible');
        cy.contains('.sw-button--ghost', 'Add discount').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .clear()
            .type('10');

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@patchPromotion').its('response.statusCode').should('equal', 204);

        // Verify promotion in Administration
        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--name`, 'New year promotion');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--active .is--active`)
            .should('be.visible');

        // Navigate to order list page
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // navigate to order create page
        cy.contains('Add order').click();

        cy.get('.sw-order-create-initial-modal').should('be.visible');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 input').check();

        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-order-create-initial-modal__tab-product').should('not.be.disabled');
        cy.get('.sw-order-create-initial-modal__tab-product').click();

        // continue adding a valid line item
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group')
            .click();

        // expect a new table row visible
        cy.get(`${page.elements.dataGridRow}--0`)
            .should('be.visible');

        // double click on item name cell
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`)
            .dblclick();

        // enter item name
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        // enter item quantity
        cy.get(`${page.elements.dataGridColumn}--quantity input`)
            .clearTypeAndCheck('10');

        // save line item
        cy.get(`${page.elements.dataGridInlineEditSave}`)
            .click();

        cy.wait('@addLineItem').its('response.statusCode').should('equal', 200);

        cy.get('.sw-order-create-initial-modal__tab-options').click();

        cy.get('.sw-select-selection-list__input').scrollIntoView().typeAndCheck('DISCOUNT').type('{enter}');

        cy.get('.sw-button--primary').click();

        cy.get('.sw-order-create-initial-modal').should('not.be.exist');

        // assert adding promotion tag successfully
        cy.contains(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--label`, 'New year promotion');
        cy.contains(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--quantity`, '1');
        cy.get('.sw-order-create__tab-details').click();
        cy.get('.sw-tagged-field__tag-list .sw-label').scrollIntoView().should('be.visible');
        cy.contains('.sw-tagged-field__tag-list .sw-label', 'DISCOUNT');
        cy.get('.sw-tagged-field__tag-list .sw-label').should('not.have.class', 'sw-label--danger');

        // save order
        cy.contains('Save order')
            .click();

        // deny payment reminder
        cy.get('.sw-order-create__remind-payment-modal-decline')
            .click();

        cy.wait('@saveOrder').its('response.statusCode').should('equal', 200);

        // assert saving successful
        cy.get('.sw-order-detail')
            .should('be.visible');

        cy.contains(page.elements.tabs.general.summaryMainHeader, 'Pep Eroni');

        cy.get('tbody .sw-data-grid__row').should('have.length', 2);
    });

    it('@order: add invalid promotion code', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST',
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST',
        }).as('saveOrder');

        // navigate to order create page
        cy.contains('Add order').click();

        cy.get('.sw-order-create-initial-modal').should('be.visible');
        cy.get('.sw-data-grid__body .sw-data-grid__row--0 input').check();

        cy.get('.sw-order-customer-grid__sales-channel-selection-modal').should('be.visible');
        cy.get('.sw-order-customer-grid__sales-channel-selection-modal .sw-button--primary').click();
        
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-order-create-initial-modal__tab-product').should('not.be.disabled');
        cy.get('.sw-order-create-initial-modal__tab-product').click();

        // continue adding a valid line item
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group')
            .click();

        // expect a new table row visible
        cy.get(`${page.elements.dataGridRow}--0`)
            .should('be.visible');

        // double click on item name cell
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`)
            .dblclick();

        // enter item name
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        // save line item
        cy.get(`${page.elements.dataGridInlineEditSave}`)
            .click();

        cy.wait('@addLineItem').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button--primary').click();

        cy.get('.sw-order-create-initial-modal').should('not.be.exist');

        cy.get('.sw-order-create__tab-details').click();

        cy.get('.sw-tagged-field__input').scrollIntoView().typeAndCheck('CODE').type('{enter}');

        // Verify that notification is visible to user
        cy.get('.sw-alert--error').should('be.visible');

        // save order
        cy.contains('Save order')
            .click();

        cy.get('.sw-modal__body').should('be.visible');

        cy.contains('.sw-modal__body', 'CODE');

        // assert adding promotion tag failed
        cy.get('.sw-tagged-field__tag-list .sw-label').should('be.visible');
        cy.contains('.sw-tagged-field__tag-list .sw-label', 'CODE');
        cy.get('.sw-tagged-field__tag-list .sw-label').should('have.class', 'sw-label--danger');

        // remove invalid code
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();

        // Verify that notification is visible to user
        cy.get('.sw-alert--error').should('not.exist');

        // save order
        cy.contains('Save order')
            .click();

        // deny payment reminder
        cy.get('.sw-order-create__remind-payment-modal-decline')
            .click();

        cy.wait('@saveOrder').its('response.statusCode').should('equal', 200);

        // assert saving successful
        cy.get('.sw-order-detail')
            .should('be.visible');

        cy.contains(page.elements.tabs.general.summaryMainHeader, 'Pep Eroni');
        cy.contains('.sw-order-detail__summary', '€49.98');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--quantity`, '1');
    });
});
