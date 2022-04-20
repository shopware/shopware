// / <reference types="Cypress" />

import PaymentPageObject from '../../../../support/pages/module/sw-payment.page-object';

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
    it.skip('@base @settings: create and read payment method', () => {
        const page = new PaymentPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/payment-method`,
            method: 'POST'
        }).as('saveData');

        cy.setEntitySearchable('payment_method', 'name');

        // Create customer-group
        cy.get('a[href="#/sw/settings/payment/create"]').click();
        cy.get('#sw-field--paymentMethod-name').typeAndCheck('Bar bei Abholung');
        cy.get('#sw-field--paymentMethod-position').type('10');
        cy.get('input[name="sw-field--paymentMethod-active"]').click();
        cy.get(page.elements.paymentSaveAction).click();

        // Verify and check usage of payment method
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Bar bei Abholung');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Bar bei Abholung').should('be.visible');
    });

    // ToDo: NEXT-20936 - Find payment method in new list
    it.skip('@base @settings: update and read payment method', () => {
        const page = new PaymentPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/payment-method/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.setEntitySearchable('payment_method', 'name');

        // Edit base data
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.clickContextMenuItem(
            '.sw-settings-payment-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('#sw-field--paymentMethod-name').clearTypeAndCheck('In Schokoladentafeln');
        cy.get(page.elements.paymentSaveAction).click();

        // Verify and check usage of payment method
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('In Schokoladentafeln');
        cy.contains(`${page.elements.dataGridRow}--0`, 'In Schokoladentafeln').should('be.visible');
    });

    it('@base @settings: delete payment method', () => {
        const page = new PaymentPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/payment-method/*`,
            method: 'delete'
        }).as('deleteData');

        cy.setEntitySearchable('payment_method', 'name');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete the payment method "CredStick"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify and check usage of payment-method
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('not.exist');
    });
});
