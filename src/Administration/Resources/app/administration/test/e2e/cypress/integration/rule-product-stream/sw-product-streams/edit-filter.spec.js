/// <reference types="Cypress" />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Test various filters', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });
    });

    it('@base @rule: edit filter', () => {
        const page = new ProductStreamObject();

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        page.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'Yes'
            }
        );

        page.clickProductStreamFilterOption(cy.get('.sw-product-stream-filter'), 'Create before');

        cy.get('.sw-product-stream-filter').first().as('first');
        page.fillFilterWithEntitySelect(
           '@first',
            {
                field: 'Product',
                operator: 'Is equal to',
                value: 'Product name'
            }
        );

        page.clickProductStreamFilterOption(cy.get('.sw-product-stream-filter').last(), 'Delete');

        cy.get('.sw-product-stream-filter').should(($productStreamFilter) => {
            expect($productStreamFilter).to.have.length(1);
        });

        cy.get('button.sw-button').contains('Save').click();
        cy.get('button.sw-button .icon--small-default-checkmark-line-medium').should('be.visible');
    });
});
