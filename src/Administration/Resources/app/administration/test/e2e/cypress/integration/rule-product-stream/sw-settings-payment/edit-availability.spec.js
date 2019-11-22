// / <reference types="Cypress" />

import PaymentPageObject from '../../../support/pages/module/sw-payment.page-object';
import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Payment: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('payment-method');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/index`);
            });
    });

    it('@package @rule: edit availability rule', () => {
        const page = new PaymentPageObject();
        const rulePage = new RulePageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/payment-method/**',
            method: 'patch'
        }).as('saveData');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.clickContextMenuItem(
            '.sw-settings-payment-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Open modal and create new availability rule
        cy.get('.sw-settings-payment-detail__condition_container .sw-select-rule-create').click();
        cy.get('.sw-select-result-list').should('be.visible');
        cy.get('.sw-select-result').contains('Create new rule...').click();

        cy.get('.sw-modal.sw-rule-modal').should('be.visible');

        cy.get('.sw-modal.sw-rule-modal').within(() => {
            cy.get('input[name=sw-field--rule-name]').type('Rule for new customers');
            cy.get('input[name=sw-field--rule-priority]').type('1');

            rulePage.createBasicSelectCondition({
                type: 'Is new customer',
                selector: '.sw-condition',
                operator: null,
                value: 'Yes',
            });

            cy.get('button.sw-button').contains('Save').click();
        });

        cy.get('.sw-modal.sw-rule-modal').should('not.exist');
        cy.awaitAndCheckNotification('The rule "Rule for new customers" has been saved.');

        cy.get('.sw-select-rule-create').contains('Rule for new customers');

        // Save and verify payment method
        cy.get(page.elements.paymentSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).contains('CredStick');
    });
});
