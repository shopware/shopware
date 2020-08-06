/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Base price', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('unit');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: Editing product with base price', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Set base price data
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Measures & Packaging').scrollIntoView();
        cy.get('.sw-select-product__select_unit').typeSingleSelectAndCheck('Gramm','.sw-select-product__select_unit');
        cy.get('.sw-product-packaging-form__purchase-unit-field').type('50');
        cy.get('.sw-product-packaging-form__pack-unit-field').type('Package');
        cy.get('.sw-product-packaging-form__pack-unit-plural-field').type('Packages');
        cy.get('.sw-product-packaging-form__reference-unit-field').type('100');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-price-unit').contains('Content: 50 Gramm (€128.00* / 100 Gramm)');

        cy.get('input[name=search]').type('Product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product name')
            .click();

        cy.get('.product-detail-price-unit').contains('Content: 50 Gramm (€128.00* / 100 Gramm)')
        cy.get('.product-detail-price').contains('64.00');

        cy.get('.btn-buy').click();
        cy.get('.cart-item-price').contains('€64.00*');
        cy.get('.cart-item-reference-price').contains('€128.00* / 100 Gramm');
    });
});
