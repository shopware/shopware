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

    it('@package @rule: create and read rule', () => {
        const page = new RulePageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/rule?_response=true',
            method: 'post'
        }).as('saveData');

        cy.get('a[href="#/sw/settings/rule/create"]').click();

        // Create rule
        cy.get('.field--condition').contains('Search conditions...');
        page.createBasicRule('Rule 1st');

        // Verify rule
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Rule 1st');
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Rule 1st');
    });

    it('@package @rule: delete rule', () => {
        const page = new RulePageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/rule/*',
            method: 'delete'
        }).as('deleteData');

        // Delete rule
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-listing__confirm-delete-text')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
    });
});
