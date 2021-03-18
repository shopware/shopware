/// <reference types="Cypress" />

describe('User: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
                    .then(() => {
                        cy.openInitialPage(`${Cypress.env('admin')}#/sw/users/permissions/index`);
                    });
            });
    });

    it('@settings: create and delete user', () => {
        // Requests we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/user`,
            method: 'post'
        }).as('searchCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/user`,
            method: 'post'
        }).as('createCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/user/**`,
            method: 'delete'
        }).as('deleteCall');
        cy.route({
            url: '/api/oauth/token',
            method: 'post'
        }).as('oauthCall');

        // create a new user
        cy.get('.sw-users-permissions-user-listing__add-user-button')
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
            .click();

        cy.wait('@oauthCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@createCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-modal')
            .should('not.be.visible');

        // should be able to delete the user
        cy.get('a.smart-bar__back-btn').click();

        cy.clickContextMenuItem(
            '.sw-settings-user-list__user-delete-action',
            '.sw-context-button__button',
            '.sw-data-grid__row--0'
        );

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Warning');

        cy.get('.sw-modal__footer > .sw-button--danger')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@deleteCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-modal')
            .should('not.be.visible');

        cy.awaitAndCheckNotification('User "Abraham Allison " deleted.');
    });

    it('@settings: update existing user', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/oauth/token',
            method: 'post'
        }).as('oauthCall');

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
            .click();

        cy.wait('@oauthCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-modal')
            .should('not.be.visible');

        cy.get('#sw-field--user-email')
            .should('be.visible')
            .should('have.value', 'changed@shopware.com');
    });

    it('@settings: can not create a user with an invalid field', () => {
        // Requests we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/user`,
            method: 'post'
        }).as('createCall');
        cy.route({
            url: '/api/oauth/token',
            method: 'post'
        }).as('oauthCall');

        // create a new user
        cy.get('.sw-users-permissions-user-listing__add-user-button')
            .should('be.visible')
            .click();

        // fill in the user information
        const userFields = {
            '#sw-field--user-lastName': 'Allison',
            '#sw-field--user-email': 'test@shopware.com',
            '#sw-field--user-username': 'abraham'
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
            .click();

        cy.wait('@oauthCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@createCall').then((xhr) => {
            expect(xhr).to.have.property('status', 400);
        });

        cy.get('.sw-modal')
            .should('not.be.visible');

        cy.get('.sw-settings-user-detail__grid-firstName .sw-field__error')
            .should('be.visible')
            .contains('This field must not be empty.');
        cy.get('.sw-settings-user-detail__grid-password .sw-field__error')
            .should('be.visible')
            .contains('This field must not be empty.');
    });
});
