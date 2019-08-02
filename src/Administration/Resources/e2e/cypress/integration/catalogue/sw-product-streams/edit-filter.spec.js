// / <reference types="Cypress" />

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

    it('@rule: edit filter', () => {
        const page = new ProductStreamObject();

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw_product_stream_list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('1st Productstream');

        page.createBasicSelectCondition({
            type: 'Active',
            ruleSelector: `${page.elements.baseCondition}`,
            value: 'Yes'
        });
        cy.clickContextMenuItem(
            '.sw-condition-base__create-before-action',
            page.elements.contextMenuButton,
            `${page.elements.conditionAndContainer}--0`
        );
        page.createBasicSelectCondition({
            type: 'Product',
            operator: 'Is equal to any of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--0 ${page.elements.baseCondition}`,
            value: 'Product name',
            isMulti: true
        });

        cy.get(`${page.elements.conditionAndContainer}--0 .sw-select__single-selection`).contains('Product');
        cy.get('.sw-product-stream-detail__condition_container').scrollIntoView();
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.conditionAndContainer}--1`
        );

        cy.get(`${page.elements.conditionAndContainer}--1 ${page.elements.baseCondition}`)
            .should('not.exist');
        cy.contains('Delete container').click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`).should('not.exist');
        cy.contains('Delete container').should('be.disabled');
    });
});
