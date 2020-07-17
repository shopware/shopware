// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }]
                });
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

    it('@base @catalogue: add variant to product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product/*',
            method: 'patch'
        }).as('saveData');

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

        // Create and verify one-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Yellow');
        cy.get('.sw-data-grid__body').contains('Green');
        cy.get('.sw-data-grid__body').contains('.1');
        cy.get('.sw-data-grid__body').contains('.2');
        cy.get('.sw-data-grid__body').contains('.3');

        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name')
            .first()
            .should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name')
            .first()
            .dblclick();
        cy.get('.is--inline-edit .sw-data-grid__cell--price-EUR .sw-inheritance-switch').should('be.visible');
        cy.get('.is--inline-edit .sw-data-grid__cell--price-EUR .sw-inheritance-switch').click();
        cy.get('.sw-data-grid__cell--price-EUR #sw-price-field-gross').should('be.visible');
        cy.get('.is--inline-edit .sw-data-grid__cell--price-EUR .sw-inheritance-switch').click();
        cy.pause();
    });
});
