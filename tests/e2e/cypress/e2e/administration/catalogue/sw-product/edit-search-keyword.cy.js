// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

const page = new ProductPageObject();

function setCustomSearchKeywordIsSearchable() {
    cy.window().then(($w) => {
        $w.Shopware.Module.getModuleByEntityName('product')
            .manifest.defaultSearchConfiguration.customSearchKeywords._searchable = true;
    });
}

describe('Product: Search Keyword product', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@catalogue: edit a product\'s search keyword', { tags: ['pa-inventory'] }, () => {
        setCustomSearchKeywordIsSearchable();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            method: 'POST',
            url: `${Cypress.env('apiPath')}/search/product`,
        }).as('searchData');

        cy.contains(`${page.elements.dataGridRow}--0`, 'Product name');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Create new search keyword
        cy.get('.sw-product-category-form__search-keyword-field input').clear();
        cy.get('.sw-product-category-form__search-keyword-field input').type('YTN');
        cy.get('.sw-product-category-form__search-keyword-field input')
            .type('{enter}');

        // To loose keyword list focus
        cy.get('.sw-product-variant-info__product-name').click();

        // Save product with search keyword
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 200);

        cy.get(page.elements.successIcon).should('be.visible');

        cy.get(page.elements.smartBarBack).click();

        cy.wait('@searchData').its('response.statusCode').should('equal', 200);
        cy.get('input.sw-search-bar__input').type('YTN');

        cy.get('.sw-product-list-grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // Ensure the search found the correct product
        cy.contains(`${page.elements.dataGridRow}--0`, 'Product name');
    });
});
