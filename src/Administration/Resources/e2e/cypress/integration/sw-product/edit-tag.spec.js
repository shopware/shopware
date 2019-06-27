// / <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';

describe('Product: Tagging product', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('tag');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('edit a product\'s tags', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/product/*',
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Use existing ones
        cy.get('.sw-tag-field').click();
        cy.get('.sw-tag-field input').type('Schöner');
        cy.contains('Schöner Tag').click();
        cy.get('.sw-tag-field .sw-label').contains('Schöner Tag');

        // Add existing tag
        cy.get(`.product-basic-form ${page.elements.loader}`).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('Product name');
        cy.get('.sw-tag-field').should('be.visible');

        // Create new tag
        cy.get('.sw-tag-field .sw-multi-select__selection-item-input input').clear();
        page.createTag('What does it means[TM]???', 1);

        // Save product with tag
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.successIcon).should('be.visible');
        });
    });
});
