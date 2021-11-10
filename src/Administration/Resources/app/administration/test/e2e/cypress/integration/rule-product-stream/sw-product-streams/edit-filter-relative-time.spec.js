// / <reference types='Cypress' />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Test relative time filters', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createProductFixture({
                    name: 'First product',
                    productNumber: 'RS-11111',
                    active: true,
                    releaseDate: '2099-12-12'
                }).then(() => {
                    cy.createProductFixture({
                        name: 'Second product',
                        productNumber: 'RS-22222',
                        active: true,
                        releaseDate: '2099-12-12'
                    });
                }).then(() => {
                    cy.createProductFixture({
                        name: 'Third product',
                        productNumber: 'RS-33333',
                        active: true,
                        releaseDate: '2019-12-12'
                    });
                });
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            });
    });

    it('@base @rule: can preview products with relative time filters', () => {
        const productStreamPage = new ProductStreamObject();
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productStreamPage.elements.contextMenuButton,
            `${productStreamPage.elements.dataGridRow}--0`
        );
        cy.get(productStreamPage.elements.smartBarHeader).contains('1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Release date',
                operator: 'Time until date',
                value: 'Is greater than'
            }
        );
        cy.get('#sw-field--stringValue').typeAndCheck('5');

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').should('contain', 'Preview (2)');
            cy.get('.sw-data-grid .sw-data-grid__cell--name').should('contain', 'First product');
            cy.get('.sw-data-grid .sw-data-grid__cell--name').should('contain', 'Second product');
            cy.get('.sw-data-grid .sw-data-grid__cell--name').should('not.contain', 'Third product');
            cy.get('.sw-modal__close').click();
        });

        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Release date',
                operator: 'Time since date',
                value: 'Is greater than'
            }
        );
        cy.get('#sw-field--stringValue').typeAndCheck('5');

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').should('contain', 'Preview (1)');
            cy.get('.sw-data-grid .sw-data-grid__cell--name').should('contain', 'Third product');
            cy.get('.sw-data-grid .sw-data-grid__cell--name').should('not.contain', 'First product');
            cy.get('.sw-data-grid .sw-data-grid__cell--name').should('not.contain', 'Second product');
        });
    });
});
