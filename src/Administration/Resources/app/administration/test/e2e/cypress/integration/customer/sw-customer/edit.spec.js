// / <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

describe('Customer:  Edit in various ways', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
            });
    });

    it('@customer: edit customer via inline edit', () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/customer/*',
            method: 'patch'
        }).as('saveData');

        // Inline edit customer
        cy.get('.sw-data-grid__cell--customerNumber').dblclick();
        cy.get(page.elements.inlineEditIndicator).should('be.visible');
        cy.get('#sw-field--item-firstName').clear().type('Woody');
        cy.get('#sw-field--item-lastName').clear().type('Ech');
        cy.get(page.elements.dataGridInlineEditSave).click();

        // Verify updated customer
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-data-grid__cell--firstName').contains('Woody Ech');
    });
});
