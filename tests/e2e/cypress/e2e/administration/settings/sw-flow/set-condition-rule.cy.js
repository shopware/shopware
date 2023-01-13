// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Set rule for condition sequence testing', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        });
    });

    it('@settings: create new rule for condition sequence', { tags: ['pa-business-ops'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'POST',
        }).as('getRule');

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
        cy.get('.sw-flow-sequence-selector__add-condition').click();
        cy.get('.sw-flow-sequence-condition__selection-rule').click();

        cy.wait('@getRule').its('response.statusCode').should('equal', 200);

        cy.get('.sw-select-result__create-new-rule').click();

        cy.get('.sw-flow-rule-modal').should('be.visible');

        cy.get('.sw-flow-rule-modal__name').type('Time rule');
        cy.get('.sw-flow-rule-modal__priority').type('1000');

        cy.get('.sw-flow-rule-modal__tab-rule').click();

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

        cy.get('.sw-flow-rule-modal__save-button').click();
        cy.get('.sw-flow-rule-modal').should('not.exist');

        cy.contains('.sw-flow-sequence-condition__rule-name', 'Time rule');

        // Check in rule builder
        cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        cy.get('.sw-rule-list-grid').should('exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Time rule');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.contains(`${page.elements.dataGridRow}--0`, 'Time rule').should('be.visible');
    });

    it('@settings: update rule for condition sequence', { tags: ['pa-business-ops'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        const page = new SettingsPageObject();

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
        cy.get('.sw-flow-sequence-selector__add-condition').click();

        cy.get('.sw-flow-sequence-condition__selection-rule')
            .typeSingleSelect('All customers', '.sw-flow-sequence-condition__selection-rule');

        cy.contains('.sw-flow-sequence-condition__rule-name', 'All customers');

        // Change rule
        cy.get('.sw-flow-sequence-condition__rule-context-button').click();
        cy.get('.sw-flow-sequence-condition__rule-change').click();

        cy.get('.sw-flow-sequence-condition__selection-rule')
            .typeSingleSelect('Sunday sales', '.sw-flow-sequence-condition__selection-rule');

        cy.contains('.sw-flow-sequence-condition__rule-name', 'Sunday sales');

        // Edit rule
        cy.get('.sw-flow-sequence-condition__rule-context-button').click();
        cy.get('.sw-flow-sequence-condition__rule-edit').click();

        // Rule modal opens
        cy.get('.sw-flow-rule-modal').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-flow-rule-modal__name').clear();
        cy.get('.sw-flow-rule-modal__name').type('Weekend sales');

        cy.get('.sw-flow-rule-modal__tab-rule').click();
        cy.contains('.sw-condition-type-select', 'Day of the week');
        cy.contains('.sw-condition-operator-select', 'Is equal to');
        cy.contains('.sw-condition-tree', 'Sunday');

        cy.get('.sw-flow-rule-modal__save-button').click();
        cy.get('.sw-flow-rule-modal').should('not.exist');

        cy.contains('.sw-flow-sequence-condition__rule-name', 'Weekend sales');

        // Check in rule builder
        cy.visit(`${Cypress.env('admin')}#/sw/settings/rule/index`);
        cy.get('.sw-rule-list-grid').should('exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Weekend sales');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.contains(`${page.elements.dataGridRow}--0`, 'Weekend sales').should('be.visible');
    });
});
