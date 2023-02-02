/// <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: flow detail page', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        })
            .then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: show warning modal when unsaved changes on flow detail page', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        cy.get(`${page.elements.dataGridRow}--0 a`).click();

        // custom name input
        cy.get('#sw-field--flow-name').clear().type('Custom name');
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-flow-leave-page-modal').should('be.visible');
        cy.get('.sw-flow-leave-page-modal__stay-on-page').click();

        // custom description input
        cy.get('#sw-field--flow-description').clear().type('Custom description');
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-flow-leave-page-modal').should('be.visible');
        cy.get('.sw-flow-leave-page-modal__stay-on-page').click();

        // custom priority input
        cy.get('#sw-field--flow-priority').clear().type('12');
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-flow-leave-page-modal').should('be.visible');
        cy.get('.sw-flow-leave-page-modal__stay-on-page').click();

        // custom active checkbox
        cy.get('.sw-flow-detail-general__general-active input[type=checkbox]').check();
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-flow-leave-page-modal').should('be.visible');
        cy.get('.sw-flow-leave-page-modal__leave-page').click();

        cy.get(`${page.elements.dataGridRow}--0 a`).click();

        cy.contains('#sw-field--flow-name', 'Custom name').should('not.exist');
        cy.contains('#sw-field--flow-description', 'Custom description').should('not.exist');
        cy.get('.sw-flow-detail__tab-flow').click();

        cy.get('.sw-flow-sequence-action__header .sw-flow-sequence-action__context-button').click();
        cy.get('.sw-flow-sequence-action__delete-action-container').click();

        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-flow-leave-page-modal__leave-page').click();

        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('.sw-flow-detail__tab-flow').click();

        cy.get('.sw-flow-sequence').should('be.visible');
    });
});
