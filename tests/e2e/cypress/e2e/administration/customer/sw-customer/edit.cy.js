// / <reference types="Cypress" />

import CustomerPageObject from '../../../../support/pages/module/sw-customer.page-object';

describe('Customer:  Edit in various ways', () => {
    beforeEach(() => {
        cy.createCustomerFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@customer: edit customer via inline edit', { tags: ['pa-customers-orders'] }, () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/customer/*`,
            method: 'PATCH',
        }).as('saveData');

        // Inline edit customer
        cy.get('.sw-data-grid__cell--customerNumber').dblclick();
        cy.get(page.elements.inlineEditIndicator).should('be.visible');
        cy.get('#sw-field--item-firstName').clear().type('Woody');
        cy.get('#sw-field--item-lastName').clear().type('Ech');
        cy.get(page.elements.dataGridInlineEditSave).click();

        // Verify updated customer
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.contains('.sw-data-grid__cell--firstName', 'Ech, Woody');
    });

    it('@customer: edit customer via detail page', { tags: ['pa-customers-orders'] }, () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/customer/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-customer-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('#sw-field--customer-firstName').clearTypeAndCheck('Woody');
        cy.get('#sw-field--customer-lastName').clearTypeAndCheck('Ech');

        cy.get('.sw-customer-base-info').scrollIntoView();

        cy.get('.sw-customer-base-info__payment-select').typeSingleSelectAndCheck(
            'Direct Debit',
            '.sw-customer-base-info__payment-select',
        );

        cy.get('.sw-customer-base-info__language-select').typeSingleSelectAndCheck(
            'Deutsch',
            '.sw-customer-base-info__language-select',
        );

        cy.get(page.elements.customerSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('Customer "Woody Ech" has been saved.');

        cy.get('.sw-skeleton').should('not.exist');
        cy.contains('.sw-customer-card__metadata-customer-name', 'Mr. Woody Ech - shopware AG');
        cy.contains('.sw-customer-base__label-default-payment-method', 'Direct Debit');
        cy.contains('.sw-customer-base__label-language', 'Deutsch');
    });
});
