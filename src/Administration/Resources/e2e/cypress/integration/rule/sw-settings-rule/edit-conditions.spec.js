// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@rule: edit rule conditions', () => {
        // TODO Split up in smaller tests if the test run time won't get too high
        const page = new RulePageObject();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        page.createBasicSelectCondition({
            type: 'Shipping free product',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'No',
            isMulti: false
        });

        // Add an and-condition as subcondition to the rule
        cy.get('.sw-condition-and-container__actions--and').click();
        cy.get('.condition-content__spacer--and').should('be.visible');
        cy.get(`${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1`)
            .should('be.visible');

        page.createBasicInputCondition({
            type: 'Cart amount',
            inputName: 'amount',
            operator: 'Is greater than',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1 ${page.elements.baseCondition}`,
            value: '100'
        });

        // Add another and-condition before the second one
        cy.clickContextMenuItem(
            '.sw-condition-base__create-before-action',
            page.elements.contextMenuButton,
            `${page.elements.conditionAndContainer}--1`
        );

        page.createBasicSelectCondition({
            type: 'Customer group',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1 ${page.elements.baseCondition}`,
            value: 'Standard customer group',
            isMulti: true
        });

        cy.get(`${page.elements.conditionAndContainer}--1 ${page.elements.ruleFieldCondition}`)
            .should('attr', 'title', 'Customer group');

        // Add another and-condition to the rule after the second one
        cy.clickContextMenuItem(
            '.sw-condition-base__create-after-action',
            page.elements.contextMenuButton,
            `${page.elements.conditionAndContainer}--1`
        );

        page.createBasicSelectCondition({
            type: 'Billing country',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--2 ${page.elements.baseCondition}`,
            value: 'Australia',
            isMulti: true
        });
        cy.get(`${page.elements.conditionAndContainer}--2 ${page.elements.ruleFieldCondition}`)
            .should('attr', 'title', 'Billing country');

        // Create second main condition as or-condition
        cy.get('.sw-condition-or-container__actions--or').click();
        cy.get(page.elements.orSpacer).should('be.visible');

        page.createBasicSelectCondition({
            type: 'Is new customer',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`,
            value: 'No',
            isMulti: false
        });

        // Add an or-condition as sub condition to that second one'
        cy.get(`${page.elements.conditionOrContainer}--1 .sw-condition-and-container__actions--sub`).click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1`).should('be.visible');
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`).scrollIntoView();

        page.createBasicInputCondition({
            type: 'Last name',
            inputName: 'lastName',
            operator: 'Is not equal to',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'Norris'
        });

        cy.get(`${page.elements.conditionOrContainer}--1 .sw-condition-or-container__actions--or`).click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`)
            .should('be.visible');
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer} ${page.elements.orSpacer}`)
            .should('be.visible');
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.andSpacer}`).scrollIntoView();

        page.createBasicSelectCondition({
            type: 'Billing country',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`,
            value: 'Greece',
            isMulti: true
        });

        // Save rule with nested condition
        cy.get(page.elements.successIcon).should('not.exist');
        cy.get(page.elements.ruleSaveAction).click();
        cy.get(page.elements.successIcon).should('be.visible');

        // Delete single condition
        cy.get('.sw-condition-container__or-child--1').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`
        );
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`)
            .should('not.exist');

        // Delete single container
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.ruleDeleteAction}`).click();
        cy.get(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`)
            .should('not.exist');

        // Delete all containers
        cy.get(page.elements.ruleDeleteAction).click();
        cy.get(page.elements.ruleFieldCondition)
            .should('attr', 'title', '');

        cy.get(page.elements.ruleSaveAction).click();
        cy.awaitAndCheckNotification('An error occurred while saving rule "Ruler".');
        cy.get('.sw-condition-base__error-container').contains('Placeholder cannot be saved.');
    });
});
