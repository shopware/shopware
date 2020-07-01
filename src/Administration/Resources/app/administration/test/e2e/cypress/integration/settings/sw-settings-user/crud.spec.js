// / <reference types="Cypress" />

describe('User: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
                    .then(() => {
                        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/user/list`);
                    });
            });
    });

    it('@settings: user form change email', () => {
        // create a new user
        cy.get('.sw-settings-user-list__create-user-action')
            .should('be.visible')
            .click();

        // fill in the user information
        const userFields = {
            '#sw-field--user-firstName': 'Abraham',
            '#sw-field--user-lastName': 'Allison',
            '#sw-field--user-email': 'test@shopware.com',
            '#sw-field--user-username': 'abraham',
            '.sw-field--password__container > input[type=password]': 'mesecurepassword'
        };

        Object.keys(userFields).forEach((key) => {
            cy.get(key)
                .should('be.visible')
                .clear()
                .type(userFields[key]);
        });

        // expect successful save
        cy.get('.sw-settings-user-detail__save-action')
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

                // should be able to delete the user
                cy.visit(`${Cypress.env('admin')}#/sw/settings/user/list`);

                cy.clickContextMenuItem(
                    '.sw-settings-user-list__user-delete-action',
                    '.sw-context-button__button',
                    '.sw-data-grid__row--0'
                );

                // expect modal to be open
                cy.get('.sw-modal')
                    .should('be.visible');
                cy.get('.sw-modal__title')
                    .contains('Delete user');


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

                        cy.awaitAndCheckNotification('User "Abraham Allison " has been deleted.');
                    });
            });
    });

    it('@settings: user form change email', () => {
        cy.clickContextMenuItem(
            '.sw-settings-user-list__user-view-action',
            '.sw-context-button__button',
            '.sw-data-grid__row--0'
        );

        cy.get('#sw-field--user-email')
            .should('be.visible')
            .click()
            .clear()
            .type('changed@shopware.com');

        cy.get('.sw-settings-user-detail__save-action')
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

                cy.get('#sw-field--user-email')
                    .should('be.visible')
                    .should('have.value', 'changed@shopware.com');
            });
    });
});
