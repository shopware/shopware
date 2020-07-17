// / <reference types="Cypress" />

describe('Profile module', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
                    .then(() => {
                        cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);
                    });
            });
    });

    it('@base @general: profile change email', () => {
        cy.get('#sw-field--email')
            .should('be.visible')
            .click()
            .clear()
            .type('changed@shopware.com');

        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Enter your current password to confirm');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('not.be.visible');

                cy.get('#sw-field--email')
                    .should('be.visible')
                    .should('have.value', 'changed@shopware.com');
            });
    });

    it('@base @general: profile raise a notification for invalid authentification', () => {
        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Enter your current password to confirm');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('erawpohs');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('be.visible');

                cy.awaitAndCheckNotification('Please submit your current password correctly.');
            });
    });
});
