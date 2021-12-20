/// <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';

describe('Administration & Storefront: Country settings tax free for B2C and B2B', () => {

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.createProductFixture({
                name: 'Product name',
                productNumber: 'TEST-1234',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    net: 11,
                    linked: true,
                    gross: 15
                }]
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
        });
    });
    const page = new ProductPageObject();

    it('@package: should validate tax free with B2C', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST'
        }).as('getCountrySettings');
        cy.intercept({
            url: '/account/register',
            method: 'POST'
        }).as('registerCustomer');

        // Add product to sales channel
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains('h2','Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck('E2E install test');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan').should('be.visible');

        // Set tax free for customers - B2C
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Netherlands');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get(`.sw-data-grid__cell--name`).contains('Netherlands').click();
        cy.get('input[name="sw-field--country-customerTax-enabled"]').check();
        cy.get('input#sw-field--country-customerTax-amount').clearTypeAndCheck('10');
        cy.get('.sw-button-process__content').click();
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);

        // Registration B2C
        cy.visit('/account/register');
        cy.url().should('include', '/account/register');
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
        cy.url().should('include', 'account');

        // Add product to cart
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Product name');

        // Go to cart and validate tax free for B2C
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.get('.cart-item-details-container [title]').contains('Product name');
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('€ 11,00*');
        cy.get('.header-cart-total').contains('€ 11,00*');
    });

    it('@package: should validate tax free with B2B', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: 'api/_action/system-config/batch',
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true
                    }
                }
            };
            return cy.request(requestConfig);
        });

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST'
        }).as('getCountrySettings');
        cy.intercept({
            url: `/account/register`,
            method: 'POST'
        }).as('registerCustomer');

        // Add product to sales channel
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains('h2','Product name').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck('E2E install test');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan').should('be.visible');

        // Set tax free for companies - B2B
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Netherlands');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get(`.sw-data-grid__cell--name`).contains('Netherlands').click();
        cy.get('input[name="sw-field--country-companyTax-enabled"]').check();
        cy.get('input#sw-field--country-companyTax-amount').clearTypeAndCheck('10');
        cy.get('.sw-button-process__content').click();
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);

        //Registration B2B
        cy.visit('/account/register');
        cy.get('#accountType').select('Commercial');
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#billingAddresscompany').typeAndCheckStorefront('shopware AG');
        cy.get('#vatIds').typeAndCheckStorefront('DE123456789');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Add product to cart
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Product name');

        // Go to cart and validate tax free for B2B
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.get('.cart-item-details-container [title]').contains('Product name');
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('€ 11,00*');
        cy.get('.header-cart-total').contains('€ 11,00*');
    });
});
