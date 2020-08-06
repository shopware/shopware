/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test crud operations', () => {
    before(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            });
    });

    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue @percy: check basic product appearance', () => {
        const page = new ProductPageObject();

        cy.takeSnapshot('Product listing');

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.takeSnapshot('Product detail base', '.sw-product-detail-base');
    });

    it('@catalogue @percy: check product property appearance', () => {
        const page = new ProductPageObject();

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-advanced-prices').should('be.visible');
        cy.get('.sw-product-detail__tab-advanced-prices').click();

        cy.get('.sw-product-detail-context-prices__empty-state-card').should('be.visible');
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.contains('.sw-select-result', 'All customers').click();

        cy.takeSnapshot('Product detail - Advanced prices', '.sw-product-detail-context-prices');
    });

    it('@catalogue @percy: check product property appearance', () => {
        const page = new ProductPageObject();

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-properties').should('be.visible');
        cy.get('.sw-product-detail__tab-properties').click();

        cy.get('#sw-field--searchTerm').should('be.visible');
        cy.get('#sw-field--searchTerm').click();
        cy.get('.sw-property-search__tree-selection').should('be.visible');
        cy.contains('.sw-grid__cell-content', 'Color').click();
        cy.contains('.sw-grid__row--0 .sw-grid__cell-content', 'Green').should('be.visible');
        cy.get('.sw-grid__row--0 input').click();

        cy.takeSnapshot('Product detail - Properties', '.sw-property-assignment__label-content');
    });

    it('@catalogue @percy: check product variant appearance', () => {
        const page = new ProductPageObject();

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
    });
});
