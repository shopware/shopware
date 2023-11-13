/// <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const page = new ProductPageObject();
const checkoutPage = new CheckoutPageObject();

describe('Add an advance pricing rule and make an order', { tags: ['pa-inventory'] }, () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Test Product',
            productNumber: 'TS-444',
            price: [{
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                linked: true,
                gross: 60,
            }],
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: should add advance pricing option to the standard product', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        const productPrice = 60;
        const advancePriceStandard = 50;
        const gridRowOne = '[class="sw-data-grid__row sw-data-grid__row--1"]';

        cy.url().should('include', 'product/index');

        // Add advance pricing for the standard product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-product-detail__tab-advanced-prices').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .typeSingleSelect('All customers', '.sw-product-detail-context-prices__empty-state-select-rule');
        cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
        cy.get('[placeholder="∞"').should('be.visible').clearTypeAndCheck('5');
        cy.get('[placeholder="∞"').type('{enter}');
        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('input#sw-price-field-net').first().should('have.value', '42');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().clearTypeAndCheck(advancePriceStandard);
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .type('{enter}');
        cy.get(`${gridRowOne} .sw-price-field__net [type]`).first()
            .should('have.value', '42.016806722689');
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('h2', 'Test Product').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Check the standard product with advance pricing from the Storefront
        cy.visit('/');

        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.contains(':nth-child(1) > .product-block-prices-cell-thin', 'To 5');
        cy.contains(':nth-child(2) > .product-block-prices-cell-thin', 'From 6');
        cy.contains('.product-detail-name', 'Test Product');
        cy.get('tr:nth-of-type(1)  div').should('include.text', productPrice);
        cy.get('tr:nth-of-type(2)  div').should('include.text', advancePriceStandard);
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas, verify test product price is 60€
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');
        cy.get('.summary-value.summary-total').should('include.text', '60,00');

        // Verify test product price is 300€ with 5 products
        cy.get('.line-item-quantity-group > .form-control').clear().type('5{enter}');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.summary-value.summary-total').should('include.text', '300,00');

        // Set the product number 6 to see whether the advance price option is applied (6x50)
        cy.get('.line-item-quantity-group > .form-control').clear().type('6{enter}');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.summary-value.summary-total').should('include.text', '300,00');
    });
});
