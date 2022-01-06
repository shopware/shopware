/// <reference types="Cypress" />
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const page = new ProductPageObject();
const checkoutPage = new CheckoutPageObject();

describe('Add an advance pricing rule and make an order', () => {
    beforeEach(() => {
        cy.loginViaApi().then(() => {
            return cy.createPropertyFixture({
                options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
            });
        }).then(() => {
            return cy.createPropertyFixture({
                name: 'Size',
                options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }]
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Test Product',
                productNumber: 'TS-444',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    linked: true,
                    gross: 60
                }]
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Variant Product',
                productNumber: 'VS-555',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    linked: true,
                    gross: 60
                }]
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
        });
    });

    it('@package: should add advance pricing option to both standard and variant product', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST'
        }).as('getUserConfig');

        const productPrice = 60;
        const advancePriceStandard = 50;
        const gridRowOne = '[class="sw-data-grid__row sw-data-grid__row--1"]';

        cy.url().should('include', 'product/index');

        // Add advance pricing for the standard product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--1`
        );
        cy.get('.sw-product-detail__tab-advanced-prices').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .typeSingleSelect('All customers', '.sw-product-detail-context-prices__empty-state-select-rule');
        cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
        cy.get('[placeholder="∞"').should('be.visible').clearTypeAndCheck('5');
        cy.get('[placeholder="∞"').type('{enter}');
        cy.get('.sw-data-grid__row--1').should('be.visible');
        cy.get('input#sw-price-field-net').first().should('have.value', '42');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().clearTypeAndCheck(advancePriceStandard);
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .type('{enter}');
        cy.get(`${gridRowOne} .sw-price-field__net [type]`).first()
            .should('have.value', '42.016806722689');
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Navigate to variant generator listing and start
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.contains('.sw-button--ghost', 'Variantengenerator starten').click();

        // Add a group to create a multidimensional variant
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Size', [0], 1);
        cy.get('.sw-product-variants__generate-action').should('be.visible');
        cy.get('.sw-product-variants__generate-action').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Color', [0, 1], 2);
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
        cy.get('.sw-product-variants-overview').should('be.visible');

        // Add advance pricing for the first variant product
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--name > .sw-data-grid__cell-content').click();
        cy.url().should('include', 'product/detail');
        cy.get('.sw-product-detail__tab-advanced-prices').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule').should('have.attr', 'disabled');
        cy.get('[type="checkbox"]').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule').should('not.have.attr', 'disabled');
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .typeSingleSelect('All customers', '.sw-product-detail-context-prices__empty-state-select-rule');
        cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
        cy.get('[placeholder="∞"').should('be.visible');
        cy.get('[placeholder="∞"').type('10 {enter}');
        cy.get('.sw-data-grid__row--1').should('be.visible');
        cy.get('input#sw-price-field-net').first().should('have.value', '42');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().clearTypeAndCheck('55');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .type('{enter}');
        cy.get(`${gridRowOne} .sw-price-field__net [type]`).first()
            .should('have.value', '46.218487394958');
        cy.get(page.elements.productSaveAction).click();
        cy.get('.sw-loader').should('not.exist');

        // Add advance pricing for the second variant product
        cy.get('.sw-card__back-link').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid__row--1 > .sw-data-grid__cell--name > .sw-data-grid__cell-content').click();
        cy.url().should('include', 'product/detail');
        cy.get('.sw-product-detail__tab-advanced-prices').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule').should('have.attr', 'disabled');
        cy.get('[type="checkbox"]').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule').should('not.have.attr', 'disabled');
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .typeSingleSelect('All customers', '.sw-product-detail-context-prices__empty-state-select-rule');
        cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
        cy.get('[placeholder="∞"').should('be.visible');
        cy.get('[placeholder="∞"').type('10 {enter}');
        cy.get('.sw-data-grid__row--1').should('be.visible');
        cy.get('input#sw-price-field-net').first().should('have.value', '42');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().clearTypeAndCheck('54');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .type('{enter}');
        cy.get(`${gridRowOne} .sw-price-field__net [type]`).first()
            .should('have.value', '45.378151260504');
        cy.get(page.elements.productSaveAction).click();
        cy.get('.sw-loader').should('not.exist');

        // Add products to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.url().should('include', 'product/index');
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.link.link-primary').click();
        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('.sw-product-bulk-edit-modal').should('exist');
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.get('.smart-bar__header').contains('Bulk edit: 2 products');
        cy.get('.sw-bulk-edit-change-field-visibilities [type="checkbox"]').click();
        cy.get('div[name="visibilities"]').typeMultiSelectAndCheck('E2E install test');

        // Save and apply changes
        cy.get('.sw-bulk-edit-product__save-action').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.footer-right .sw-button--primary').contains('Apply changes');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.sw-bulk-edit-save-modal').contains('Bulk edit - Success');
        cy.get('.footer-right .sw-button--primary').contains('Sluiten');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');

        // Check the variant product from the store front
        cy.visit('/');
        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Variant Product');
        cy.contains('.search-suggest-product-name', 'Variant Product').click();
        cy.get(':nth-child(1) > .product-block-prices-cell-thin').contains('To 10');
        cy.get(':nth-child(2) > .product-block-prices-cell-thin').contains('From 11');

        // Check the standard product with advance pricing from the store front
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get(':nth-child(1) > .product-block-prices-cell-thin').contains('To 5');
        cy.get(':nth-child(2) > .product-block-prices-cell-thin').contains('From 6');
        cy.get('.product-detail-name').contains('Test Product');
        cy.get('tr:nth-of-type(1)  div').should('include.text', productPrice);
        cy.get('tr:nth-of-type(2)  div').should('include.text', advancePriceStandard);
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas, verify test product price is 60€
        cy.get(`${checkoutPage.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${checkoutPage.elements.cartItem}-label`).contains('Test Product');
        cy.get('.summary-value.summary-total').should('include.text', '60,00');

        // Verify test product price is 300€ with 5 products
        cy.get('.cart-item-quantity-container > .custom-select').select('5');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.summary-value.summary-total').should('include.text', '300,00');

        // Set the product number 6 to see whether the advance price option is applied (6x50)
        cy.get('.cart-item-quantity-container > .custom-select').select('6');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.summary-value.summary-total').should('include.text', '300,00');
    });
});
