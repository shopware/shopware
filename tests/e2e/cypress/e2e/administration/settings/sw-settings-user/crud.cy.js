// / <reference types="Cypress" />

describe('User: Test crud operations', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/users/permissions/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: create and delete user', { tags: ['pa-system-settings'] }, () => {
        // Requests we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user`,
            method: 'POST',
        }).as('createCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/user/**`,
            method: 'delete',
        }).as('deleteCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
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
            '.sw-field--password__container > input[type=password]': 'mesecurepassword',
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
        cy.contains('.sw-modal__title', 'Confirm password');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').its('response.statusCode').should('equal', 200);

        cy.wait('@createCall').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal')
            .should('not.exist');

        // should be able to delete the user
        cy.get('a.smart-bar__back-btn').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user`,
            method: 'POST',
        }).as('userSearchCall');

        cy.get('.sw-users-permissions-user-listing .sw-simple-search-field')
            .type('abraham');

        cy.wait('@userSearchCall').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-users-permissions-user-listing .sw-data-grid__row--0', 'abraham');

        cy.clickContextMenuItem(
            '.sw-settings-user-list__user-delete-action',
            '.sw-context-button__button',
            '.sw-data-grid__row--0',
        );

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw-modal__title', 'Warning');

        cy.get('.sw-modal__footer > .sw-button--danger')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@deleteCall').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal')
            .should('not.exist');

        cy.awaitAndCheckNotification('User "Abraham Allison " deleted.');
    });

    it('@settings: update existing user', { tags: ['pa-system-settings', 'quarantined'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
        }).as('oauthCall');

        cy.clickContextMenuItem(
            '.sw-settings-user-list__user-view-action',
            '.sw-context-button__button',
            '.sw-data-grid__row--0',
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
        cy.contains('.sw-modal__title', 'Confirm password');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').its('response.statusCode').should('equal', 200);

        cy.get('.sw-modal')
            .should('not.exist');

        cy.get('#sw-field--user-email')
            .should('be.visible')
            .should('have.value', 'changed@shopware.com');
    });

    it('@settings: can not create a user with an invalid field', { tags: ['pa-system-settings', 'quarantined'] }, () => {
        // Requests we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user`,
            method: 'POST',
        }).as('createCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
        }).as('oauthCall');

        // create a new user
        cy.get('.sw-users-permissions-user-listing__add-user-button')
            .should('be.visible')
            .click();

        // fill in the user information
        const userFields = {
            '#sw-field--user-lastName': 'Allison',
            '#sw-field--user-email': 'test@shopware.com',
            '#sw-field--user-username': 'abraham',
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
        cy.contains('.sw-modal__title', 'Confirm password');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').its('response.statusCode').should('equal', 200);

        cy.wait('@createCall').its('response.statusCode').should('equal', 400);

        cy.get('.sw-modal')
            .should('not.exist');

        cy.get('.sw-settings-user-detail__grid-firstName')
            .scrollIntoView();

        cy.contains('.sw-settings-user-detail__grid-firstName .sw-field__error', 'This field must not be empty.')
            .should('be.visible');
        cy.contains('.sw-settings-user-detail__grid-password .sw-field__error', 'This field must not be empty.')
            .should('be.visible');
    });
});
