// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

const uuid = require('uuid/v4');

describe('Rule builder: Test viewing rule assignments in other entities', () => {
    const defaultRuleId = uuid().replace(/-/g, '');

    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('rule', { id: defaultRuleId, name: 'Default Rule' }, 'rule-simple-condition');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/detail/${defaultRuleId}`);
                // wait for ending loading state
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@rule: edit rule then switch tab and discard changes', { tags: ['pa-business-ops'] }, () => {
        cy.onlyOnFeature('V6_5_0_0');

        const page = new RulePageObject();

        cy.get('.sw-settings-rule-detail-base').should('be.visible');
        cy.get('input#sw-field--rule-name').should('be.visible');

        // Change the rule name
        cy.get('input#sw-field--rule-name').clearTypeAndCheck('New Name');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Save changes modal should open
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__title').contains('Discard unsaved changes?');

        // Discard changes
        cy.get('.sw-button--danger').contains('Discard changes').click();

        // wait for ending loading state
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-settings-rule-detail-assignments').should('be.visible');

        // Switch back to general tab and verify the name did not change
        cy.get('.sw-settings-rule-detail__tab-item-general').click();

        // wait for ending loading state
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input#sw-field--rule-name').should('have.value', 'Default Rule');
    });

    it('@rule: edit rule condition then switch tab and discard changes', { tags: ['pa-business-ops'] }, () => {
        cy.onlyOnFeature('V6_5_0_0');

        const page = new RulePageObject();

        cy.get('.sw-settings-rule-detail-base').should('be.visible');
        cy.get('input#sw-field--rule-name').should('be.visible');

        // Change the rule conditions
        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');
        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').as('condition-general');

            page.createBasicSelectCondition({
                selector: '@condition-general',
                type: 'Item with free shipping',
                operator: null,
                value: 'No'
            });
        });

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Save changes modal should open
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__title').contains('Discard unsaved changes?');

        // Discard changes
        cy.get('.sw-button--danger').contains('Discard changes').click();

        // wait for ending loading state
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-settings-rule-detail-assignments').should('be.visible');

        // Switch back to general tab and verify the name did not change
        cy.get('.sw-settings-rule-detail__tab-item-general').click();

        // wait for ending loading state
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input#sw-field--rule-name').should('have.value', 'Default Rule');
    });

    it('@rule: edit rule and condition leave route and discard changes', { tags: ['pa-business-ops'] }, () => {
        cy.onlyOnFeature('V6_5_0_0');

        const page = new RulePageObject();

        // Change the rule name
        cy.get('input#sw-field--rule-name').clearTypeAndCheck('New Name');

        // Change the rule conditions
        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');
        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').as('condition-general');

            page.createBasicSelectCondition({
                selector: '@condition-general',
                type: 'Item with free shipping',
                operator: null,
                value: 'No'
            });
        });

        // Leave route
        cy.get(page.elements.smartBarBack).click();

        // Save changes modal should open
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__title').contains('Discard unsaved changes?');

        // Discard changes
        cy.get('.sw-button--danger').contains('Discard changes').click();

        // wait for ending loading state
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-rule-list-grid').should('be.visible');
    });

    it('@rule: edit rule then save unsaved changes and switch to assignment tab', { tags: ['pa-business-ops'] }, () => {
        cy.onlyOnFeature('V6_5_0_0');

        const page = new RulePageObject();

        // Change the rule name
        cy.get('input#sw-field--rule-name').clearTypeAndCheck('New Name');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Save changes modal should open
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__title').contains('Discard unsaved changes?');

        // Keep editing
        cy.get('.sw-button').contains('Keep editing').click();
        // Save the rule
        cy.get(page.elements.ruleSaveAction).click();

        // wait for ending loading state
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();

        // Save changes modal should NOT open
        cy.get('.sw-modal').should('not.exist');
    });

    it('@rule: toggle assignment tab and toggle language and there should be no changes', { tags: ['pa-business-ops'] }, () => {
        cy.onlyOnFeature('V6_5_0_0');
        const page = new RulePageObject();

        // Switch to assignments tab
        cy.get('.sw-settings-rule-detail__tab-item-assignments').click();
        cy.get('.sw-skeleton').should('not.exist');

        // Save changes modal should NOT open
        cy.get('.sw-modal').should('not.exist');

        // Switch back to general tab
        cy.get('.sw-settings-rule-detail__tab-item-general').click();
        cy.get('.sw-skeleton').should('not.exist');

        // Save changes modal should NOT open
        cy.get('.sw-modal').should('not.exist');

        page.changeTranslation('Deutsch', 0);
        cy.get('.sw-skeleton').should('not.exist');

        // Save changes modal should NOT open
        cy.get('.sw-modal').should('not.exist');

        page.changeTranslation('English', 1);
        cy.get('.sw-skeleton').should('not.exist');

        // Save changes modal should NOT open
        cy.get('.sw-modal').should('not.exist');
    });
});
