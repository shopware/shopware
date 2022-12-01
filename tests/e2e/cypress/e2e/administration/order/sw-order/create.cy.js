// / <reference types="Cypress" />
import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Create order', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @order: create order with an existing customer', { tags: ['pa-customers-orders'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
        }).as('saveOrder');

        // navigate to order create page
        cy.contains('Add order')
            .click();

        // expect create-order-page is visible
        cy.contains('h2', 'New order');

        // expect unabling to add any line items if there is no customer yet
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('be.disabled');

        // select an existing customer
        cy.get('.sw-order-create-details-header .sw-entity-single-select')
            .typeSingleSelectAndCheck('Eroni', '.sw-order-create-details-header .sw-entity-single-select');

        // expect customer data correctly
        cy.get('.sw-order-create-details-body input[name="sw-field--email"]')
            .should('have.value', 'test@example.com');

        // expect abling to add the line items if there is an available customer
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('not.be.disabled');

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

        cy.contains(`.sw-order-detail-base ${page.elements.userMetadata}`, 'Pep Eroni');

        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--quantity`, '10');
    });

    it('@base @order: create order with a new customer, update line item and shipping cost manually', { tags: ['pa-customers-orders'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/customer`,
            method: 'POST'
        }).as('createCustomerCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'PATCH'
        }).as('updateLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/modify-shipping-costs`,
            method: 'PATCH'
        }).as('modifyShippingCostsCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
        }).as('saveOrder');

        // navigate to order create page
        cy.contains('Add order')
            .click();

        // expect create-order-page is visible
        cy.contains('h2', 'New order');

        // expect unabling to add any line items if there is no customer yet
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('be.disabled');

        // add a new customer
        cy.contains('Add new customer')
            .click();

        // expect modal add-new-customer visible
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw-modal__title', 'Add new customer');

        // expect customer-password is not disabled when customer-guest is not checked
        cy.get('.sw-order-new-customer-modal input[name="sw-field--customer-guest"]')
            .should('not.be.checked');
        cy.get('.sw-order-new-customer-modal input[name="sw-field--customer-password"]')
            .should('not.be.disabled');

        // customer-guest is checked
        cy.get('.sw-order-new-customer-modal input[name="sw-field--customer-guest"]')
            .check();
        cy.get('.sw-order-new-customer-modal input[name="sw-field--customer-password"]')
            .should('be.disabled');

        // customer-guest is uncheck
        cy.get('.sw-order-new-customer-modal input[name="sw-field--customer-guest"]')
            .uncheck();
        cy.get('.sw-order-new-customer-modal input[name="sw-field--customer-password"]')
            .should('not.be.disabled');

        // enter salutation
        cy.get('.sw-customer-base-form__salutation-select')
            .typeSingleSelectAndCheck('Mr.', '.sw-customer-base-form__salutation-select');

        // enter firstName
        cy.get('input[name="sw-field--customer-firstName"]')
            .typeAndCheck('Golden');

        // enter lastName
        cy.get('input[name="sw-field--customer-lastName"]')
            .typeAndCheck('Stars');

        // enter email
        cy.get('input[name="sw-field--customer-email"]')
            .typeAndCheck('goldenstars@example.com');

        // enter customer-group
        cy.get('.sw-customer-base-form__customer-group-select')
            .typeSingleSelectAndCheck('Standard customer group', '.sw-customer-base-form__customer-group-select');

        // enter sales-channel
        cy.get('.sw-customer-base-form__sales-channel-select')
            .typeSingleSelectAndCheck('Storefront', '.sw-customer-base-form__sales-channel-select');

        // enter payment-method
        cy.get('.sw-customer-base-form__payment-method-select')
            .typeSingleSelectAndCheck('Invoice', '.sw-customer-base-form__payment-method-select');

        // enter password
        cy.get('#sw-field--customer-password')
            .typeAndCheck('shopware');

        // change to "Billing address" tab
        cy.contains('.sw-order-new-customer-modal .sw-tabs-item', 'Billing address')
            .click();

        // enter salutation
        cy.get('.sw-customer-address-form__salutation-select')
            .typeSingleSelectAndCheck('Mr.', '.sw-customer-address-form__salutation-select');

        // enter firstName
        cy.get('input[name="sw-field--address-firstName"]')
            .typeAndCheck('Golden');

        // enter lastName
        cy.get('input[name="sw-field--address-lastName"]')
            .typeAndCheck('Stars');

        // enter street
        cy.get('input[name="sw-field--address-street"]')
            .typeAndCheck('Billing street');

        // enter zipcode
        cy.get('input[name="sw-field--address-zipcode"]')
            .typeAndCheck('0123456');

        // enter city
        cy.get('input[name="sw-field--address-city"]')
            .typeAndCheck('Berlin');

        // enter country
        cy.get('.sw-customer-address-form__country-select')
            .typeSingleSelectAndCheck('Germany', '.sw-customer-address-form__country-select');

        // change to "Shipping address" tab
        cy.contains('.sw-order-new-customer-modal .sw-tabs-item', 'Shipping address')
            .click();

        // "Same as billing address" is checked
        cy.get('input[name="sw-field--isSameBilling"]')
            .should('be.checked');
        cy.get('input[name="sw-field--address-street"]')
            .should('have.value', 'Billing street')
            .and('be.disabled');
        cy.get('input[name="sw-field--address-zipcode"]')
            .should('have.value', '0123456')
            .and('be.disabled');
        cy.get('input[name="sw-field--address-city"]')
            .should('have.value', 'Berlin')
            .and('be.disabled');

        // uncheck "Same as billing address"
        cy.get('input[name="sw-field--isSameBilling"]')
            .uncheck();
        cy.get('input[name="sw-field--address-street"]')
            .should('be.empty')
            .and('not.be.disabled');
        cy.get('input[name="sw-field--address-zipcode"]')
            .should('be.empty')
            .and('not.be.disabled');
        cy.get('input[name="sw-field--address-city"]')
            .should('be.empty')
            .and('not.be.disabled');

        // enter salutation
        cy.get('.sw-customer-address-form__salutation-select')
            .typeSingleSelectAndCheck('Mr.', '.sw-customer-address-form__salutation-select');

        // enter firstName
        cy.get('input[name="sw-field--address-firstName"]')
            .typeAndCheck('Golden');

        // enter lastName
        cy.get('input[name="sw-field--address-lastName"]')
            .typeAndCheck('Stars');

        // enter street
        cy.get('input[name="sw-field--address-street"]')
            .typeAndCheck('Shipping street');

        // enter zipcode
        cy.get('input[name="sw-field--address-zipcode"]')
            .typeAndCheck('6543210');

        // enter city
        cy.get('input[name="sw-field--address-city"]')
            .typeAndCheck('Berlin');

        // enter country
        cy.get('.sw-customer-address-form__country-select')
            .typeSingleSelectAndCheck('Germany', '.sw-customer-address-form__country-select');

        // save customer
        cy.get('.sw-modal .sw-modal__footer .sw-button--primary')
            .click({ force: true });

        cy.wait('@createCustomerCall').its('response.statusCode').should('equal', 204);

        // assert creating new customer successful with the different address
        cy.get('.sw-order-create-details-body input[name="sw-field--email"]')
            .should('have.value', 'goldenstars@example.com');
        cy.contains('.sw-order-create-details-body .is-billing .sw-address__street',
            'Billing street');
        cy.contains('.sw-order-create-details-body .is-shipping .sw-address__street', 'Shipping street');

        // expect abling to add the line items if there is an available customer
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('not.be.disabled');

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
            .clearTypeAndCheck('5');

        // save line item
        cy.get(`${page.elements.dataGridInlineEditSave}`)
            .click();

        cy.wait('@addLineItem').its('response.statusCode').should('equal', 200);

        // enter item price
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--unitPrice`)
            .dblclick();
        cy.get(`${page.elements.dataGridColumn}--unitPrice input`)
            .clearTypeAndCheck('10');

        // save line item
        cy.get(`${page.elements.dataGridInlineEditSave}`)
            .click();

        cy.wait('@updateLineItem').its('response.statusCode').should('equal', 200);

        // enter shipping cost
        cy.get('.sw-order-create-summary__data dd > div[tooltip-id]')
            .dblclick();
        cy.get('.sw-order-create-summary__data dd > div[tooltip-id] input')
            .clearTypeAndCheck('10');

        // save shipping cost
        cy.get('.sw-order-create-summary__data dd > div[tooltip-id] .sw-button--primary')
            .click({ force: true });

        cy.wait('@modifyShippingCostsCall').its('response.statusCode').should('equal', 200);

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

        cy.contains(`.sw-order-detail-base ${page.elements.userMetadata}`, 'Golden Stars');

        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--label`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0 ${page.elements.dataGridColumn}--quantity`, '5');
    });

    it('@order: add invalid promotion code', { tags: ['pa-customers-orders'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
        }).as('saveOrder');

        // navigate to order create page
        cy.contains('Add order')
            .click();

        // expect create-order-page is visible
        cy.contains('h2', 'New order');

        // expect unabling to add any line items if there is no customer yet
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('be.disabled');

        // select an existing customer
        cy.get('.sw-order-create-details-header .sw-entity-single-select')
            .typeSingleSelectAndCheck('Eroni', '.sw-order-create-details-header .sw-entity-single-select');

        // expect customer data correctly
        cy.get('.sw-order-create-details-body input[name="sw-field--email"]')
            .should('have.value', 'test@example.com');

        // expect abling to add the line items if there is an available customer
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('not.be.disabled');

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

        cy.get('.sw-tagged-field__input').typeAndCheck('CODE').type('{enter}');

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

        cy.contains('.sw-order-detail-base .sw-order-user-card__metadata', 'Pep Eroni');

        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--label > .sw-data-grid__cell-content',
            'Product name');
        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content', '10');
    });

    it('@base @order create new order', { tags: ['pa-customers-orders'] }, () => {
        cy.onlyOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart`,
            method: 'GET'
        }).as('getCart');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
        }).as('saveOrder');

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
    it.skip('@base @order: add promotion code', { tags: ['pa-customers-orders'] }, () => {
        cy.onlyOnFeature('FEATURE_NEXT_7530');

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
            method: 'POST'
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/promotion/**/discounts`,
            method: 'POST'
        }).as('saveDiscount');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'PATCH'
        }).as('patchPromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
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

    it('@base @order: add promotion code', { tags: ['quarantined', 'pa-customers-orders'] }, () => {
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        cy.visit(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.smart-bar__actions .sw-button--primary').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/promotion/**/discounts`,
            method: 'POST'
        }).as('saveDiscount');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'PATCH'
        }).as('patchPromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
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
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // select an existing customer
        cy.get('.sw-order-create-details-header .sw-entity-single-select')
            .typeSingleSelectAndCheck('Eroni', '.sw-order-create-details-header .sw-entity-single-select');

        // expect customer data correctly
        cy.get('.sw-order-create-details-body input[name="sw-field--email"]')
            .should('have.value', 'test@example.com');

        // continue adding a valid line item
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group')
            .click();

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

        cy.get('.sw-tagged-field__input').typeAndCheck('DISCOUNT').type('{enter}');

        // assert adding promotion tag successfully
        cy.get('.sw-tagged-field__tag-list .sw-label').should('be.visible');
        cy.contains('.sw-tagged-field__tag-list .sw-label', 'DISCOUNT');
        cy.get('.sw-tagged-field__tag-list .sw-label').should('not.have.class', 'sw-label--danger');

        cy.contains(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--label`, 'New year promotion');

        cy.contains(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--quantity`, '1');

        cy.contains(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--quantity`, '1');

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

        cy.contains(`.sw-order-detail-base ${page.elements.userMetadata}`, 'Pep Eroni');

        cy.get('tbody .sw-data-grid__row').should('have.length', 2);
    });

    it('@order: add invalid promotion code', { tags: ['pa-customers-orders'] }, () => {
        cy.onlyOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // network requests
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy/store-api/**/checkout/cart/line-item`,
            method: 'POST'
        }).as('addLineItem');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_proxy-order/**`,
            method: 'POST'
        }).as('saveOrder');

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
