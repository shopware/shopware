// / <reference types="Cypress" />

import PaymentPageObject from '../../../../support/pages/module/sw-payment.page-object';
import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Payment: Test crud operations', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('payment-method');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    // ToDo: NEXT-20936 - Find payment method in new list
    it('@base @rule: edit availability rule', { tags: ['quarantined', 'pa-checkout'] }, () => {
        const page = new PaymentPageObject();
        const rulePage = new RulePageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method/**`,
            method: 'PATCH'
        }).as('saveData');

        cy.setEntitySearchable('payment_method', 'name');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.clickContextMenuItem(
            '.sw-settings-payment-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Open modal and create new availability rule
        cy.get('.sw-settings-payment-detail__condition_container .sw-select-rule-create').click();
        cy.get('.sw-select-result-list').should('be.visible');
        cy.contains('.sw-select-result', 'Create new rule...').click();

        cy.get('.sw-modal.sw-rule-modal').should('be.visible');

        cy.get('.sw-modal.sw-rule-modal').within(() => {
            cy.get('input[name=sw-field--rule-name]').type('Rule for new customers');
            cy.get('input[name=sw-field--rule-priority]').type('1');

            rulePage.createBasicSelectCondition({
                type: 'New customer',
                selector: '.sw-condition',
                operator: null,
                value: 'Yes'
            });

            cy.contains('button.sw-button', 'Save').click();
        });

        cy.get('.sw-modal.sw-rule-modal').should('not.exist');
        cy.awaitAndCheckNotification('The rule "Rule for new customers" has been saved.');

        cy.contains('.sw-select-rule-create', 'Rule for new customers');

        // Save and verify payment method
        cy.get(page.elements.paymentSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(`${page.elements.dataGridRow}--0`, 'CredStick');
    });
});
