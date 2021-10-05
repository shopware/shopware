// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test use greater/lower than on zipcode condition', () => {
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

    it('@base @rule: edit rule with shipping zipcode condition', () => {
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
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Shipping postal code').click();
                });
            cy.get('.sw-condition-zipcode-type-select')
                .then((conditionZipCodeTypeSelect) => {
                    cy.wrap(conditionZipCodeTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Numeric').click();
                });
            cy.get('.sw-condition-operator-select')
                .then((conditionOperatorSelect) => {
                    cy.wrap(conditionOperatorSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Is greater than').click();
                });

            cy.get('#sw-field--zipCodes').clear().type('12345');
        });

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
    });

    it('@base @rule: edit rule with billing zipcode condition', () => {
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
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Billing postal code').click();
                });
            cy.get('.sw-condition-zipcode-type-select')
                .then((conditionZipCodeTypeSelect) => {
                    cy.wrap(conditionZipCodeTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Numeric').click();
                });
            cy.get('.sw-condition-operator-select')
                .then((conditionOperatorSelect) => {
                    cy.wrap(conditionOperatorSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Is greater than').click();
                });

            cy.get('#sw-field--zipCodes').clear().type('12345');
        });

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
    });
});
