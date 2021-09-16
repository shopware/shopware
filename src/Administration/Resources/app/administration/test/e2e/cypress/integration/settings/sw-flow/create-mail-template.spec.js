// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Create mail template for send mail action testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            }).then(() => {
                return cy.createProductFixture();
            }).then(() => {
                return cy.createCustomerFixture();
            });
    });

    it('@settings: create mail template for send mail action', () => {
        cy.onlyOnFeature('FEATURE_NEXT_8225');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);

        const page = new SettingsPageObject();
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'post'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'post'
        }).as('getMailTemplate');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v2');
        cy.get('#sw-field--flow-priority').type('12');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('checkout order placed');
        cy.get('.sw-flow-trigger__input-field').type('{enter}');

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();

        // Open Send mail modal
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Send email', '.sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-mail-send-modal__mail-template-select').click();

        cy.wait('@getMailTemplate').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-select-result__create-new-template').click();
        });

        cy.get('.sw-flow-create-mail-template-modal').should('be.visible');
        cy.get('.sw-flow-create-mail-template-modal__type')
            .typeSingleSelect('Contact form', '.sw-flow-create-mail-template-modal__type');

        cy.get('.sw-flow-create-mail-template-modal__subject').type('Successful feedback');
        cy.get('.sw-flow-create-mail-template-modal__sender-name').type('Demoshop');
        cy.get('.sw-flow-create-mail-template-modal__description').type('Successful feedback description');

        cy.get('.sw-flow-create-mail-template-modal__tab-mail-text').click();
        cy.get('div[name="content_plain"]').type('Successful');
        cy.get('div[name="content_html"]').type('Successful');

        cy.get('.sw-flow-create-mail-template-modal__save-button').click();
        cy.get('.sw-flow-create-mail-template-modal').should('not.exist');

        cy.get('.sw-flow-mail-send-modal__mail-template-select')
            .contains('Successful feedback description - Contact form');

        cy.get('.sw-flow-mail-send-modal__save-button').click();
        cy.get('.sw-flow-sequence-action__action-name').contains('Send email');
        cy.get('.sw-flow-sequence-action__action-description').contains('Template: Contact form');

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit(`${Cypress.env('admin')}#/sw/mail/template/index`);
        cy.get('input.sw-search-bar__input').type('Contact form successful feedback description');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible')
            .contains('Contact form');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--description`).should('be.visible')
            .contains('Successful feedback description');
    });
});
