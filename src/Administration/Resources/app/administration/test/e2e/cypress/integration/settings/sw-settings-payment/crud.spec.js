/// <reference types="Cypress" />

import PaymentPageObject from '../../../support/pages/module/sw-payment.page-object';

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

    it('@base @settings: create and read payment method', () => {
        const page = new PaymentPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/payment-method',
            method: 'post'
        }).as('saveData');

        // Create customer-group
        cy.get('a[href="#/sw/settings/payment/create"]').click();
        cy.get('#sw-field--paymentMethod-name').typeAndCheck('Bar bei Abholung');
        cy.get('#sw-field--paymentMethod-position').type('10');
        cy.get('input[name="sw-field--paymentMethod-active"]').click();
        cy.get(page.elements.paymentSaveAction).click();

        // Verify and check usage of payment method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Bar bei Abholung');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Bar bei Abholung');
    });

    it('@base @settings: update and read payment method', () => {
        const page = new PaymentPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/payment-method/*',
            method: 'patch'
        }).as('saveData');

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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('In Schokoladentafeln');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('In Schokoladentafeln');
    });

    it('@base @settings: delete payment method', () => {
        const page = new PaymentPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/payment-method/*',
            method: 'delete'
        }).as('deleteData');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('CredStick');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the payment method "CredStick"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify and check usage of payment-method
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('not.exist');
    });
});
