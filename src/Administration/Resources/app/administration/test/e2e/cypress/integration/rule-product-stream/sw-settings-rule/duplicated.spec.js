// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test duplication of rule', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@rule: Duplication of rules should open duplicated one for editing', () => {
        const page = new RulePageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'post'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'post'
        }).as('searchData');

        // Request for duplicate Rule
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/clone/rule/*`,
            method: 'post'
        }).as('duplicateData');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        // fill basic data
        cy.get('.sw-field').contains('.sw-field', 'Name').then((field) => {
            cy.get('input', { withinSubject: field }).type('Duplication RuleBuilder');
        });

        cy.get('.sw-field').contains('.sw-field', 'Priority').then((field) => {
            cy.get('input', { withinSubject: field }).type('1').blur();
        });

        cy.get('.sw-field').contains('.sw-field', 'Description').then((field) => {
            cy.get('textarea', { withinSubject: field }).type('desc');
        });

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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.wait('@searchData').then(({ response }) => {
            const originalId = response.body.data[0].id;
            cy.get(page.elements.smartBarBack).click();
            cy.get('.sw-search-bar__input').typeAndCheckSearchField('Duplication RuleBuilder');

            cy.get(page.elements.loader).should('not.exist');

            cy.clickContextMenuItem(
                '.sw-context-menu-item:nth-child(2)',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );

            // Verify duplicate
            cy.wait('@duplicateData').then((xhr) => {
                expect(xhr).to.have.property('status', 200);
                cy.url().should('not.contain', originalId);
            });
        });
    });
});
