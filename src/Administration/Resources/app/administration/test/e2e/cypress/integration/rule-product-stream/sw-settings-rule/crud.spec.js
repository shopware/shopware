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

    it('@base @rule: create and read rule', () => {
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

    it('@base @rule: should show the condition select upwards', () => {
        cy.get('a[href="#/sw/settings/rule/create"]').click();
        cy.get('.sw-settings-rule-detail__condition_container').scrollIntoView();

        cy.get('.sw-condition').then((conditionElement) => {
            cy.get('.sw-condition-type-select', { withinSubject: conditionElement })
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    const popover = cy.get('.sw-select-result-list-popover-wrapper');

                    popover.should('be.visible');
                    popover.should('have.class', '--placement-bottom-outside');
                });
        });
    });
});
