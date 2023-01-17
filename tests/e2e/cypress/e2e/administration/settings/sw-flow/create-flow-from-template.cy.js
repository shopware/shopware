// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Create a flow from flow template', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index/templates`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: create a flow from flow template', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.get('.sw-flow-list-my-templates').should('be.visible');
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.contains(page.elements.dataGridRow, 'Order placed')
            .find('.sw-flow-list-my-flows__content__create-flow-link')
            .click();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        // Verify successful clone
        cy.contains('.smart-bar__header h2', 'Order placed');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input input').should('not.be.checked');
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify created element
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed');
    });
});
