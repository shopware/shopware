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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @rule: read rule', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        });

        const page = new RulePageObject();

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('All customers')
            .click();

        cy.get('#sw-field--rule-name').should('have.value', 'All customers');
        cy.get('.sw-condition-type-select .sw-single-select__selection-text').contains('Customer group');
    });

    it('@base @rule: edit rule', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer'
            },
            {
                key: 'rule',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        });

        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('All customers')
            .click();

        cy.get('#sw-field--rule-name').should('have.value', 'All customers');
        cy.get('#sw-field--rule-name').clearTypeAndCheck('Some customers');

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
    });

    it('@base @rule: create and read rule', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer'
            },
            {
                key: 'rule',
                role: 'editor'
            },
            {
                key: 'rule',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        });

        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule`,
            method: 'POST'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        // save with empty data
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 400);

        cy.awaitAndCheckNotification('An error occurred while saving rule "".');

        // fill basic data
        cy.get('.sw-field').contains('.sw-field', 'Name').then((field) => {
            cy.wrap(field).should('have.class', 'has--error');
            cy.get('input', { withinSubject: field }).type('Rule 1st');
            cy.wrap(field).should('not.have.class', 'has--error');
        });

        cy.get('.sw-field').contains('.sw-field', 'Priority').then((field) => {
            cy.wrap(field).should('have.class', 'has--error');
            cy.get('input', { withinSubject: field }).type('1').blur();
            cy.wrap(field).should('not.have.class', 'has--error');
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
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Rule 1st');
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Rule 1st');
    });

    it('@base @rule: delete rule', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'rule',
                role: 'viewer'
            },
            {
                key: 'rule',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        });

        const page = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/rule/*`,
            method: 'delete'
        }).as('deleteData');

        // Delete rule
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-listing__confirm-delete-text')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
    });
});
