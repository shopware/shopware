// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Deliverability', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
        });
    });

    it('@base @catalogue: Editing product with extended purchase amounts', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: '/checkout/offcanvas',
            method: 'GET',
        }).as('offcanvasCart');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Set base price data
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-product-deliverability__min-purchase [type]').scrollIntoView().clear().typeAndCheck('50');
        cy.get('.sw-product-deliverability__purchase-step [type]').clear().typeAndCheck('10');
        cy.get('.sw-product-deliverability__max-purchase [type]').clear().typeAndCheck('200000');
        cy.get('.sw-product-detail__tab-specifications').scrollIntoView().click();
        cy.contains('.sw-card__title', 'Measures & packaging').scrollIntoView();
        cy.get('.sw-product-packaging-form__pack-unit-field').type('Package');
        cy.get('.sw-product-packaging-form__pack-unit-plural-field').type('Packages');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');

        cy.get('input[name=search]').type('Product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product name')
            .click();

        cy.contains('.buy-widget-container', 'Packages');
        cy.get('input.product-detail-quantity-input').should('have.value', '50').clear().type('1000000000{downArrow}');

        cy.get('.btn-buy').click();
        cy.get('.js-offcanvas-cart-change-quantity-number').should('have.value', '200000');
        cy.contains('.line-item-total-price', '€9,996,000.00*');

        cy.get('.js-offcanvas-cart-change-quantity-number').clear().type('1{upArrow}').blur();
        cy.wait('@offcanvasCart').its('response.statusCode').should('equal', 200);

        cy.get('.js-offcanvas-cart-change-quantity-number').should('have.value', '50');
        cy.contains('.line-item-total-price', '€2,499.00*');
    });
});
