// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

const page = new ProductPageObject();

function setCustomSearchKeywordIsSearchable() {
    cy.window().then(($w) => {
        $w.Shopware.Module.getModuleByEntityName('product')
            .manifest.defaultSearchConfiguration.customSearchKeywords._searchable = true;
    });
}

describe('Product: Search Keyword product', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: edit a product\'s search keyword', () => {
        cy.onlyOnFeature('FEATURE_NEXT_6040', () => {
            setCustomSearchKeywordIsSearchable();
        });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');

        cy.intercept({
            method: 'POST',
            url: `${Cypress.env('apiPath')}/search/product`
        }).as('searchData');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Create new search keyword
        cy.get('.sw-product-category-form__search-keyword-field input').clear();
        cy.get('.sw-product-category-form__search-keyword-field input').type('YTN');
        cy.get('.sw-product-category-form__search-keyword-field input')
            .type('{enter}');

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
        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');
    });
});
