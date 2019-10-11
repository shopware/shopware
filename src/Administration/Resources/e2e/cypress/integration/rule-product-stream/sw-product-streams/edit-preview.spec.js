// / <reference types="Cypress" />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product groups: Test dynamic product group preview', () => {
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

    it('@catalogue: check preview while editing', () => {
        const page = new ProductStreamObject();

        cy.get(page.elements.smartBarHeader).contains('Dynamic product groups');

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw_product_stream_list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.loader).should('not.exist');

        page.createBasicSelectCondition({
            type: 'Product',
            operator: 'Is equal to any of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'Product name',
            isMulti: true
        });

        cy.get('.sw-product-stream-detail__open_modal_preview').click();
        cy.get('.sw-modal__title').contains('Preview (1)');
        cy.get('.sw-product-stream-modal-preview__column-product-name').contains('Product name');

        cy.get('.sw-product-stream-modal-preview__close-action').click();
        cy.get(page.elements.modal).should('not.exist');
        cy.get('.sw-select--multi').type('{backspace}');

        page.createBasicSelectCondition({
            type: 'Product',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'Product name',
            isMulti: false
        });
        cy.get('.sw-product-stream-detail__open_modal_preview').click();
        cy.get('.sw-modal__title').contains('Preview (0)');
        cy.get('.sw-empty-state').should('be.visible');

        cy.get('.sw-product-stream-modal-preview__close-action').click();
        cy.get(page.elements.modal).should('not.exist');
    });
});
