/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import SettingsPageObject from '../../support/pages/module/sw-settings.page-object';

describe('@package: Currency: checkout with tax-free and price rounding', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Product name',
            productNumber: 'TEST-1234',
            price: [
                {
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    net: 34.115658,
                    linked: true,
                    gross: 49.115658,
                },
            ],
        });
    });

    it('Should checkout with tax-free and price rounding', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();
        const pageSettings = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/currency/**/country-roundings`,
            method: 'POST',
        }).as('saveCurrencyCountry');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/currency`,
            method: 'POST',
        }).as('getCurrencySettings');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');
        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        // Set tax-free
        cy.visit(`${Cypress.env('admin')}#/sw/settings/currency/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('EUR');
        cy.clickContextMenuItem(
            '.sw-currency-list__edit-action',
            '.sw-context-button__button',
            `.sw-data-grid__row--0`,
        );
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input#sw-field--currency-taxFreeFrom').should('be.visible');
        cy.get('input#sw-field--currency-taxFreeFrom').should('have.value', '0');
        cy.get('input#sw-field--currency-taxFreeFrom').type('{selectall}{backspace}{selectall}{backspace}10{enter}');
        cy.get('input#sw-field--currency-taxFreeFrom').should('have.value', '10');

        // Set country price rounding
        cy.get('.sw-settings-currency-detail__currency-country-toolbar-button').scrollIntoView();
        cy.get('.sw-settings-currency-detail__currency-country-toolbar-button').click();
        cy.get('.sw-settings-currency-country-modal__select-country')
            .typeSingleSelectAndCheck('Netherlands', '.sw-settings-currency-country-modal__select-country');
        cy.get('.sw-settings-currency-country-modal input[name=sw-field--itemRounding-decimals]')
            .type('{selectall}{backspace}{selectall}{backspace}3{enter}');
        cy.get('.sw-settings-currency-country-modal input[name=sw-field--itemRounding-decimals]')
            .should('have.value', '3');
        cy.get('.sw-settings-currency-country-modal input[name=sw-field--totalRounding-decimals]')
            .type('{selectall}{backspace}{selectall}{backspace}3{enter}');
        cy.get('.sw-settings-currency-country-modal input[name=sw-field--totalRounding-decimals]')
            .should('have.value', '3');
        cy.get('.sw-settings-currency-country-modal__button-save').click();
        cy.wait('@saveCurrencyCountry').its('response.statusCode').should('equal', 204);

        cy.get('.sw-alert').should('not.exist');

        // Verify country price rounding
        cy.get('.sw-settings-currency-country-modal').should('not.exist');
        cy.get('.sw-settings-currency-detail__currency-country-list').should('be.visible');
        cy.contains(`${pageSettings.elements.dataGridRow}--0 .sw-data-grid__cell--country`, 'Netherlands');
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCurrencySettings').its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('h2', 'Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView().typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Search product
        cy.visit('/');

        cy.get('.header-search-input').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.contains('.product-detail-price', '49,116');
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas').should('be.visible');
        cy.contains('.line-item-label', 'Product name');

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.contains('.line-item-details-container [title]', 'Product name');
        cy.get('.line-item-total-price').contains('34,116');
        cy.contains('.col-5.checkout-aside-summary-total', '34,116');
        cy.get('a[title="Go to checkout"]').click();

        // Register customer
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get("#personalGuest").check();
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Confirm
        cy.contains('.confirm-address', 'Test Tester');
        cy.contains('.line-item-label', 'Product name');
        cy.get('.line-item-total-price').scrollIntoView();
        cy.get('.line-item-total-price').contains('34,116');
        cy.contains('.col-5.checkout-aside-summary-total', '34,116');

        // Finish checkout
        cy.contains('.confirm-tos .card-title', 'Terms and conditions and cancellation policy');
        cy.get('.confirm-tos .form-check label').scrollIntoView();
        cy.get('.confirm-tos .form-check label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.contains('.finish-header', `Thank you for your order with E2E install test!`);

        // Verify the order from the storefront
        cy.visit('/account/login');
        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
        cy.contains('.account-overview-profile > .card > .card-body', 'test@tester.com');
        cy.get('.order-table-header-heading').should('be.visible')
            .and('include.text', 'Order');
    });
});
