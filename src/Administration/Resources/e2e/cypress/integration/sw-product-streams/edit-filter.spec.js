// / <reference types="Cypress" />

import ProductStreamObject from '../../support/pages/module/sw-product-stream.page-object';

describe('Product group: Test various filters', () => {
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

    it.skip('edit filter', () => {
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
        cy.clickContextMenuItem(
            '.sw-condition-base__create-after-action',
            page.elements.contextMenuButton,
            `${page.elements.conditionAndContainer}--1`
        );

        page.createCombinedInputSelectCondition({
            type: 'Price',
            secondValue: '100',
            firstValue: 'Gross',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            isMulti: false,
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--2 ${page.elements.baseCondition}`
        });

        cy.get(`${page.elements.conditionAndContainer}--2 .field--condition:nth-of-type(1)`).contains('Price');
        cy.get('.sw-condition-or-container__actions--or').click();
        cy.get(page.elements.orSpacer).should('be.visible');

        page.createBasicInputCondition({
            type: 'Stock',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--0`,
            value: '10'
        });

        cy.get(`${page.elements.conditionOrContainer}--1 .sw-condition-and-container__actions--sub`).click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.orSpacer}`).should('be.visible');

        page.createBasicInputCondition({
            type: 'Stock',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1`,
            value: '10'
        });
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1 .sw-condition-or-container__actions--or`)
            .click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.orSpacer}`).should('be.visible');

        page.createBasicInputCondition({
            type: 'Stock',
            inputName: 'sw-field--filterValue',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1 ${page.elements.conditionOrContainer}--1`,
            value: '10'
        });
        cy.get('.sw-product-stream-detail__condition_container').scrollIntoView();
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1 ${page.elements.conditionOrContainer}--1`
        );

        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`)
            .should('not.exist');
        cy.get(`${page.elements.conditionOrContainer}--1 button.sw-button.sw-condition-and-container__actions--delete`)
            .click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`).should('not.exist');
        cy.get('.sw-condition-and-container__actions--delete').click();
    });
});
