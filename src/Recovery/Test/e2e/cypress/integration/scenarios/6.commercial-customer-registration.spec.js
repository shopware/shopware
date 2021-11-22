/// <reference types="Cypress" />

import ProductPageObject from "../../support/pages/module/sw-product.page-object";

describe('@package: Admin & Storefront - commercial customer registration by using product created via API', () => {

    before(() => {
        cy.setToInitialState().then(() => {
            cy.createProductFixture();
        });
    });
    beforeEach(() => {
        cy.loginViaApi();
    });

    it('Add initial settings', ()=>{
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.url().should('include', 'settings/shipping/index');
        cy.setShippingMethod('Express', '10', '8');
        cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        cy.url().should('include', 'settings/payment/index');
        cy.setPaymentMethod('Invoice');
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.url().should('include', 'dashboard/index');
        cy.goToSalesChannelDetail('E2E install test')
            .selectCountryForSalesChannel('Germany')
            .selectPaymentMethodForSalesChannel('Invoice')
            .selectShippingMethodForSalesChannel('Express');
    });

    it('Add product via API', ()=>{
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'post'
        }).as('saveData');

        const page = new ProductPageObject();

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`);
        cy.contains('h2','Product name');
        cy.get('.sw-product-detail__select-visibility') .scrollIntoView().typeMultiSelectAndCheck('E2E install test');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan');

        // Login/registration settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/login/registration/index`);
        cy.url().should('include', 'settings/login/registration/index');
        cy.get('.sw-system-config--field-core-login-registration-show-account-type-selection [type]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-loader').should('not.exist');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.url().should('include', 'settings/country/index');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Germany');
        cy.get(`.sw-data-grid__cell--name`).contains('Germany').click();
        cy.get('input[name="sw-field--country-checkVatIdPattern"]').check();
        cy.get('input[name="sw-field--country-vatIdRequired"]').check();
        cy.get('input[name="sw-field--country-forceStateInRegistration"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-loader').should('not.exist');
    });

    it('Register as commercial customer and complete shopping', ()=>{
        cy.intercept({
            url: `/account/register`,
            method: 'POST'
        }).as('registerCustomer');

        cy.visit('/account/login');
        cy.url().should('include', '/account/login');
        cy.get('select#accountType').select('Commercial');
        cy.get('select#personalSalutation').select('Mr.');
        cy.get('input#personalFirstName').clear().type('Test');
        cy.get('input#personalLastName').clear().type('Tester');
        cy.get('input#billingAddresscompany').clear().type('shopware AG');
        cy.get('input#billingAddressdepartment').clear().type('QA');
        cy.get('input#vatIds').clear().type('DE123456789');
        cy.get('input#personalMail').clear().type('test6@tester.com');
        cy.get('input#personalPassword').clear().type('shopware');
        cy.get('input#billingAddressAddressStreet').clear().type('Test street');
        cy.get('input#billingAddressAddressZipcode').clear().type('12345');
        cy.get('input#billingAddressAddressCity').clear().type('Test city');
        cy.get('select#billingAddressAddressCountry').select('Germany');
        cy.get('select#billingAddressAddressCountryState').select('North Rhine-Westphalia');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Search product
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Product name').should('be.visible');

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').should('be.visible').click();
        cy.get('.cart-item-details-container [title]').contains('Product name').should('be.visible');
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('64,00').should('be.visible');

        // Total: product price + shipping
        cy.get('.col-5.checkout-aside-summary-total').contains('74,00').should('be.visible');
        cy.get('a[title="Proceed to checkout"]').should('be.visible').click();

        // Confirm
        cy.get('.confirm-address').contains('Test Tester').should('be.visible');
        cy.get('.cart-item-label').contains('Product name').should('be.visible');
        cy.get('.cart-item-total-price').scrollIntoView();
        cy.get('.cart-item-total-price').contains('64,00').should('be.visible');
        cy.get('.col-5.checkout-aside-summary-total').contains('74,00').should('be.visible');

        // Finish checkout
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy').should('be.visible');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').should('be.visible').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains(`Thank you for your order with E2E install test!`).should('be.visible');
    });

    it('Check order in admin', ()=>{
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-search-bar__input').should('be.visible').typeAndCheckSearchField('Test Tester');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--orderCustomer-firstName').contains('Tester, Test').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--amountTotal').contains('74,00').should('be.visible');
        cy.get('.sw-data-grid__skeleton').should('not.exist');
    });

});
