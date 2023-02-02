/// <reference types="Cypress" />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product groups: Test dynamic product group preview', () => {
    beforeEach(() => {
        cy.createDefaultFixture('product-stream').then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @catalogue: check preview while editing', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        cy.contains(page.elements.smartBarHeader, 'Dynamic product groups');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.contains(page.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is equal to any of',
                value: ['Product name'],
            },
        );

        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-product-stream-modal-preview').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (1)');
            cy.contains('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name', 'Product name');
            cy.get('.sw-modal__close').click();
        });

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is not equal to any of',
                value: [],
            },
        );

        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-modal').should('be.visible');

        cy.get('.sw-product-stream-modal-preview').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (0)').should('be.visible');
            cy.get('.sw-data-grid .sw-data-grid__row--0').should('not.exist');
            cy.get('.sw-empty-state').should('be.visible');
            cy.get('.sw-modal__close').click();
        });

        cy.get('.sw-product-stream-modal-preview').should('not.exist');
    });
});
