/// <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';

describe('Product creation via API and commercial customer registration', () => {
    beforeEach(() => {
        cy.createProductFixture();
    });

    it('@package: should order as commercial customer', { tags: ['pa-customers-orders'] }, () => {
        const page = new ProductPageObject();
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        // Saleschannel initial settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/shipping/index');
        cy.setShippingMethod('Express', '10', '8');
        cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/payment/overview');
        cy.setPaymentMethod('Paid in advance');
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'dashboard/index');
        cy.goToSalesChannelDetail(Cypress.env('storefrontName'))
            .selectCountryForSalesChannel('Germany')
            .selectPaymentMethodForSalesChannel('Paid in advance')
            .selectShippingMethodForSalesChannel('Express');

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`);
        cy.contains('h2', 'Product name');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView().typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan');

        // Login/registration settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/login/registration/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/login/registration/index');
        cy.get('.sw-system-config--field-core-login-registration-show-account-type-selection [type]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-settings-country-list').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/country/index');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Germany');
        cy.get(`.sw-data-grid__cell--name`).contains('Germany').click();
        cy.get('[name="sw-field--country-checkVatIdPattern"]').check();
        cy.get('[name="sw-field--country-vatIdRequired"]').check();

        // Country handling tab
        cy.get('.sw-settings-country__address-handling-tab').click();
        cy.get('[name="sw-field--country-postalCodeRequired"]').check();
        cy.get('[name="sw-field--country-checkPostalCodePattern"]').check();
        cy.get('[name="sw-field--country-checkAdvancedPostalCodePattern"]').check();

        cy.get('[name="sw-field--country-forceStateInRegistration"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Register as commercial customer
        cy.visit('/account/login');

        cy.url().should('include', '/account/login');
        cy.get('#accountType').select('Commercial');
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#billingAddresscompany').typeAndCheckStorefront('shopware AG');
        cy.get('#billingAddressdepartment').typeAndCheckStorefront('QA');
        cy.get('#vatIds').typeAndCheckStorefront('DE123456789');
        cy.get('#personalMail').typeAndCheckStorefront('test6@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Germany');
        cy.get('#billingAddressAddressCountryState').select('North Rhine-Westphalia');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Make an order
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-buy .btn-buy').click();
        cy.get('.offcanvas').should('be.visible');
        cy.contains('.line-item-label', 'Product name').should('be.visible');
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').should('be.visible').click();
        cy.contains('.line-item-details-container [title]', 'Product name').should('be.visible');
        cy.contains('.line-item-total-price .line-item-total-price-value', '49,98').should('be.visible');
        cy.contains('.col-5.checkout-aside-summary-total', '59,98').should('be.visible');
        cy.get('a[title="Go to checkout"]').should('be.visible').click();
        cy.contains('.confirm-address', 'Test Tester').should('be.visible');
        cy.contains('.line-item-label', 'Product name').should('be.visible');
        cy.get('.line-item-total-price').scrollIntoView();
        cy.contains('.line-item-total-price', '49,98').should('be.visible');
        cy.contains('.col-5.checkout-aside-summary-total', '59,98').should('be.visible');
        cy.contains('.confirm-tos .card-title', 'Terms and conditions and cancellation policy').should('be.visible');
        cy.get('.confirm-tos .form-check label').scrollIntoView();
        cy.get('.confirm-tos .form-check label').should('be.visible').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.contains('.finish-header', `Thank you for your order with E2E install test!`).should('be.visible');

        // Verify order
        cy.visit('/account/order');
        cy.contains('.order-item-header', '10000');
        cy.contains('Expand').click();
        cy.contains('.line-item-total-price-value', '49,98');
        cy.contains('.order-item-detail-summary', '59,98');
    });
});
