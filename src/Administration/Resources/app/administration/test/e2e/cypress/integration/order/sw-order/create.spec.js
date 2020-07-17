// / <reference types="Cypress" />

describe('Order: Create order', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.loginViaApi();
            })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
            });
    });

    it.skip('Select an existing customer, add one of each items (product, custom and credit), change the amount of one item and save the order', () => {
        // start server
        cy.server();

        // network requests
        cy.route({
            url: '/api/v*/_proxy/sales-channel-api/**/v*/checkout/cart/product/*',
            method: 'post'
        }).as('addProductCall');

        cy.route({
            url: '/api/v*/_proxy/sales-channel-api/**/v*/checkout/order',
            method: 'post'
        }).as('saveOrderCall');

        // navigate to order create page
        cy.contains('Add order')
            .click();

        // expect create-order-page is visible
        cy.get('h2')
            .contains('New order');

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
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group > button')
            .click();

        // expect a new table row visible
        cy.get('.sw-data-grid__row--0')
            .should('be.visible');

        // double click on item name cell
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label')
            .dblclick();

        // enter item name
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        // enter item quantity
        cy.get('.sw-data-grid__cell--quantity input')
            .clearTypeAndCheck('10');

        // save line item
        cy.get('.sw-data-grid__inline-edit-save')
            .click();

        cy.wait('@addProductCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.response.body.data).to.have.property('token');
        });

        // save order
        cy.contains('Save order')
            .click();

        cy.wait('@saveOrderCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            assert.isNotNull(xhr.response.body.data);
        });

        // assert saving successful
        cy.get('.sw-order-detail')
            .should('be.visible');
        cy.get('.sw-order-detail-base .sw-order-user-card__metadata')
            .children()
            .contains('Pep Eroni');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label > .sw-data-grid__cell-content')
            .children()
            .contains('Product name');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content')
            .contains('10');
    });

    it.skip('Create a new customer, add an existing product, change the product price as well as the shipping costs and save the order', () => {
        // start server
        cy.server();

        // network requests
        cy.route({
            url: '/api/v*/customer',
            method: 'post'
        }).as('createCustomerCall');

        cy.route({
            url: '/api/v*/_proxy/sales-channel-api/**/v*/checkout/cart/product/*',
            method: 'post'
        }).as('addProductCall');

        cy.route({
            url: '/api/v*/_proxy/sales-channel-api/**/v*/checkout/cart/line-item/*',
            method: 'patch'
        }).as('updateProductCall');

        cy.route({
            url: '/api/v*/_proxy/modify-shipping-costs',
            method: 'patch'
        }).as('modifyShippingCostsCall');

        cy.route({
            url: '/api/v*/_proxy/sales-channel-api/**/v*/checkout/order',
            method: 'post'
        }).as('saveOrderCall');

        // navigate to order create page
        cy.contains('Add order')
            .click();

        // expect create-order-page is visible
        cy.get('h2')
            .contains('New order');

        // expect unabling to add any line items if there is no customer yet
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('be.disabled');

        // add a new customer
        cy.contains('Add new customer')
            .click();

        // expect modal add-new-customer visible
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Add new customer');

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
        cy.get('.sw-order-new-customer-modal .sw-tabs-item')
            .contains('Billing address')
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
        cy.get('.sw-order-new-customer-modal .sw-tabs-item')
            .contains('Shipping address')
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

        cy.wait('@createCustomerCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // assert creating new customer successful with the different address
        cy.get('.sw-order-create-details-body input[name="sw-field--email"]')
            .should('have.value', 'goldenstars@example.com');
        cy.get('.sw-order-create-details-body .is-billing .sw-address__street')
            .contains('Billing street');
        cy.get('.sw-order-create-details-body .is-shipping .sw-address__street')
            .contains('Shipping street');

        // expect abling to add the line items if there is an available customer
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group button')
            .should('not.be.disabled');

        // continue adding a valid line item
        cy.get('.sw-order-line-items-grid-sales-channel__actions-container .sw-button-group > button')
            .click();

        // expect a new table row visible
        cy.get('.sw-data-grid__row--0')
            .should('be.visible');

        // double click on item name cell
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label')
            .dblclick();

        // enter item name
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        // enter item quantity
        cy.get('.sw-data-grid__cell--quantity input')
            .clearTypeAndCheck('5');

        // save line item
        cy.get('.sw-data-grid__inline-edit-save')
            .click();

        cy.wait('@addProductCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.response.body.data).to.have.property('token');
        });

        // enter item price
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--unitPrice')
            .dblclick();
        cy.get('.sw-data-grid__cell--unitPrice input')
            .clearTypeAndCheck('10');

        // save line item
        cy.get('.sw-data-grid__inline-edit-save')
            .click();

        cy.wait('@updateProductCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.response.body.data).to.have.property('token');
        });

        // enter shipping cost
        cy.get('.sw-order-create-summary__data dd > div[tooltip-id]')
            .dblclick();
        cy.get('.sw-order-create-summary__data dd > div[tooltip-id] input')
            .clearTypeAndCheck('10');

        // save shipping cost
        cy.get('.sw-order-create-summary__data dd > div[tooltip-id] .sw-button--primary')
            .click({ force: true });

        cy.wait('@modifyShippingCostsCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.response.body.data).to.have.property('token');
        });

        // save order
        cy.contains('Save order')
            .click();

        cy.wait('@saveOrderCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            assert.isNotNull(xhr.response.body.data);
        });

        // assert saving successful
        cy.get('.sw-order-detail')
            .should('be.visible');
        cy.get('.sw-order-detail-base .sw-order-user-card__metadata')
            .children()
            .contains('Golden Stars');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label > .sw-data-grid__cell-content')
            .children()
            .contains('Product name');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content')
            .contains('5');
    });
});
