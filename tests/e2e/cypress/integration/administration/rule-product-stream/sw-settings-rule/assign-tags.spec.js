// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test assigning tags', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@base @rule: create rule with tags and verify assignment', () => {
        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'POST'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        // fill basic data
        cy.get('.sw-field').contains('.sw-field', 'Name').then((field) => {
            cy.get('input', { withinSubject: field }).type('Rule 1st');
            cy.wrap(field).should('not.have.class', 'has--error');
        });

        cy.get('.sw-field').contains('.sw-field', 'Priority').then((field) => {
            cy.get('input', { withinSubject: field }).type('1').blur();
            cy.wrap(field).should('not.have.class', 'has--error');
        });

        // assign tag
        cy.get('.sw-settings-rule-detail__tags-field input')
            .type('New Tag');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Add "New Tag"');
        cy.get('.sw-settings-rule-detail__tags-field input')
            .type('{enter}');
        cy.get('.sw-select-result-list-popover-wrapper').contains('New Tag');
        cy.get('.sw-settings-rule-detail__tags-field input').type('{esc}');

        // fill rule data
        cy.get('.sw-condition').then((conditionElement) => {
            cy.get('.sw-condition-type-select', { withinSubject: conditionElement })
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');

                    cy.get('.sw-select-result-list-popover-wrapper').contains('Time range')
                        .click();
                });
        });

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Go to tag administration and verify assignment
        cy.visit(`${Cypress.env('admin')}#/sw/settings/tag/index`);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'New Tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/1(\s)*rule/);
    });
});
