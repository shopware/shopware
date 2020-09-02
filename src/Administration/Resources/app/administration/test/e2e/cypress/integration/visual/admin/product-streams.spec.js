/// <reference types="Cypress" />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product groups: Visual tests', () => {
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

    it('@visual: check appearance of basic product stream workflow', () => {
        const page = new ProductStreamObject();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__row--0').should('be.visible');

        cy.changeElementStyling(
            '.sw-data-grid__cell--updatedAt',
            'color: #fff'
        );
        cy.takeSnapshot('Product groups -  Listing', '.sw-product-stream-list');

        cy.get(page.elements.smartBarHeader).contains('Dynamic product groups');

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(page.elements.smartBarHeader).contains('1st Productstream');
        cy.get(page.elements.loader).should('not.exist');

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is equal to any of',
                value: ['Product name']
            }
        );

        cy.get('button.sw-button').contains('Preview').click();
        // Take snapshot for visual testing
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('Product groups -  Preview', '.sw-product-stream-modal-preview');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (1)');
            cy.get('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Product name');
            cy.get('.sw-modal__close').click();
        });

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is not equal to any of',
                value: []
            }
        );

        // Take snapshot for visual testing
        cy.takeSnapshot('Product groups -  Detail with conditions', '.sw-product-stream-detail');
    });
});
