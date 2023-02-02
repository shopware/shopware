// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Create mail template for send mail action testing', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        });
    });

    it('@settings: create mail template for send mail action', { tags: ['pa-business-ops'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        const page = new SettingsPageObject();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST',
        }).as('getMailTemplate');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v2');
        cy.get('#sw-field--flow-priority').type('12');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').should('be.visible');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-flow-trigger__input-field').type('checkout order placed');
        cy.get('.sw-flow-trigger__input-field').type('{enter}');

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();

        // Open Send mail modal
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Send email', '.sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-mail-send-modal__mail-template-select').click();

        cy.wait('@getMailTemplate').its('response.statusCode').should('equal', 200);

        cy.get('.sw-select-result__create-new-template').click();

        cy.get('.sw-flow-create-mail-template-modal').should('be.visible');
        cy.get('.sw-flow-create-mail-template-modal__type')
            .typeSingleSelect('Contact form', '.sw-flow-create-mail-template-modal__type');

        cy.get('.sw-flow-create-mail-template-modal__subject').type('Successful feedback');
        cy.get('.sw-flow-create-mail-template-modal__sender-name').type('Demoshop');
        cy.get('.sw-flow-create-mail-template-modal__description').type('Successful feedback description');

        cy.get('div[name="content_plain"]').type('Successful');
        cy.get('div[name="content_html"]').type('Successful');

        cy.get('.sw-flow-create-mail-template-modal__save-button').click();
        cy.get('.sw-flow-create-mail-template-modal').should('not.exist');

        cy.contains('.sw-flow-mail-send-modal__mail-template-select',
            'Successful feedback description - Contact form');

        cy.get('.sw-flow-mail-send-modal__save-button').click();
        cy.contains('.sw-flow-sequence-action__action-name', 'Send email');
        cy.contains('.sw-flow-sequence-action__action-description', 'Template: Contact form');

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.visit(`${Cypress.env('admin')}#/sw/mail/template/index`);
        cy.get('.sw-mail-templates-list-grid').should('exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-empty-state').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST',
        }).as('getMailTemplateAfterSearch');

        cy.get('input.sw-search-bar__input').type('Contact form successful feedback description');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.wait('@getMailTemplateAfterSearch').its('response.statusCode').should('equal', 200);

        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--mailTemplateType-name`, 'Contact form')
            .should('be.visible');

        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--description`, 'Successful feedback description')
            .should('be.visible');
    });
});
