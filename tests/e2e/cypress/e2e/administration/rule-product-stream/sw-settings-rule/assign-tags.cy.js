// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test assigning tags', () => {
    beforeEach(() => {
        cy.createDefaultFixture('rule').then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @rule: create rule with tags and verify assignment', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-settings-rule-detail-base').should('exist');

        // fill basic data
        cy.contains('.sw-field', 'Name').then((field) => {
            cy.get('input', { withinSubject: field }).type('Rule 1st');
            cy.wrap(field).should('not.have.class', 'has--error');
        });

        cy.contains('.sw-field', 'Priority').then((field) => {
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
        cy.get('.sw-condition-type-select').click();

        cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Time range')
            .click();

        cy.get('.sw-condition-time-range').should('exist');

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        // Go to tag administration and verify assignment
        cy.visit(`${Cypress.env('admin')}#/sw/settings/tag/index`);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'New Tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--rules`).contains(/1(\s)*rule/);
    });
});
