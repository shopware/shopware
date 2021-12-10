/// <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import SettingsPageObject from '../../support/pages/module/sw-settings.page-object';

describe('@package: Currency: checkout with tax-free and price rounding', () => {

    before(() => {
        cy.setToInitialState().then(() => {
            cy.createProductFixture({
                name: 'Product name',
                productNumber: 'TEST-1234',
                price: [
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        net: 34.115658,
                        linked: true,
                        gross: 49.115658
                    }
                ]
            });
        });
    });

    beforeEach(() => {
        cy.loginViaApi();
    });

    it('Should checkout with tax-free and price rounding', () => {
        const page = new ProductPageObject();
        const pageSettings = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/currency/**/country-roundings`,
            method: 'POST'
        }).as('saveCurrencyCountry');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/currency`,
            method: 'POST'
        }).as('getCurrencySettings');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');
        cy.intercept({
            url: `/account/register`,
            method: 'POST'
        }).as('registerCustomer');

        // Set tax-free
        cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
        cy.clickContextMenuItem(
            '.sw-currency-list__edit-action',
            '.sw-context-button__button',
            `.sw-data-grid__row--0`
        );
        cy.get('input#sw-field--currency-taxFreeFrom').clear().typeAndCheck('10');

        // Set country price rounding
        cy.get('.sw-settings-currency-detail__currency-country-toolbar-button').click();
        cy.get('.sw-settings-currency-country-modal__select-country')
            .typeSingleSelectAndCheck('Netherlands', '.sw-settings-currency-country-modal__select-country');
        cy.get('.sw-settings-currency-country-modal input[name=sw-field--itemRounding-decimals]')
            .clearTypeAndCheck('3');
        cy.get('.sw-settings-currency-country-modal input[name=sw-field--totalRounding-decimals]')
            .clearTypeAndCheck('3');
        cy.get('.sw-settings-currency-country-modal__button-save').click();
        cy.wait('@saveCurrencyCountry').its('response.statusCode').should('equal', 204);

        // Verify country price rounding
        cy.get('.sw-settings-currency-country-modal').should('not.exist');
        cy.get('.sw-settings-currency-detail__currency-country-list').should('be.visible');
        cy.get(`${pageSettings.elements.dataGridRow}--0 .sw-data-grid__cell--country`).contains('Netherlands');
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCurrencySettings').its('response.statusCode').should('equal', 200);

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains('h2','Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView().typeMultiSelectAndCheck('E2E install test');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan').should('be.visible');

        // Search product
        cy.visit('/');
        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-price').contains('49,116');
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Product name');

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.get('.cart-item-details-container [title]').contains('Product name');
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('34,116');
        cy.get('.col-5.checkout-aside-summary-total').contains('34,116');
        cy.get('a[title="Proceed to checkout"]').click();

        // Register customer
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Confirm
        cy.get('.confirm-address').contains('Test Tester');
        cy.get('.cart-item-label').contains('Product name');
        cy.get('.cart-item-total-price').scrollIntoView();
        cy.get('.cart-item-total-price').contains('34,116');
        cy.get('.col-5.checkout-aside-summary-total').contains('34,116');

        // Finish checkout
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains(`Thank you for your order with E2E install test!`);
    });

    it('Should check the order in admin', ()=>{
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Test Tester');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--orderCustomer-firstName').contains('Tester, Test');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--amountTotal').contains('34,116');
        cy.get('.sw-data-grid__skeleton').should('not.exist');
    });
});
