// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Create a flow from flow template', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.loginViaApi().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index/templates`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@settings: create a flow from flow template', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.get('.sw-flow-listing__tab-flow-templates').click();

        cy.get('.sw-flow-list-my-templates').should('be.visible');
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--0`)
            .find('.sw-flow-list-my-flows__content__create-flow-link')
            .click();

        // Verify successful clone
        cy.contains('.smart-bar__header h2', 'Order placed');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input input').should('not.be.checked')
        cy.get('.sw-flow-detail__save').click();

        // Verify created element
        cy.get(page.elements.smartBarBack).click({force: true});
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Order placed');
    });
});
