// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Create new rule for condition sequence testing', () => {
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

    it('@settings: create new rule for condition sequence', () => {
        cy.onlyOnFeature('FEATURE_NEXT_8225');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);

        const page = new SettingsPageObject();
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'post'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'post'
        }).as('getRule');

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
        cy.get('.sw-flow-sequence-selector__add-condition').click();
        cy.get('.sw-flow-sequence-condition__selection-rule').click();

        cy.wait('@getRule').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-select-result__create-new-rule').click();
        });

        cy.get('.sw-flow-create-rule-modal').should('be.visible');

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

        cy.get('.sw-flow-create-rule-modal__tab-detail').click();

        cy.get('.sw-flow-create-rule-modal__name').type('Time rule');
        cy.get('.sw-flow-create-rule-modal__priority').type('1000');

        cy.get('.sw-flow-create-rule-modal__save-button').click();
        cy.get('.sw-flow-create-rule-modal').should('not.exist');

        cy.get('.sw-flow-sequence-condition__rule-name').contains('Time rule');

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Time rule');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Time rule');
    });
});
