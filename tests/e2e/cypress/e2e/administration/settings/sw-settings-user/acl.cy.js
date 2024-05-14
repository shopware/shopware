// / <reference types="Cypress" />

describe('User: Test acl privileges', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
    });

    it('@settings: view user', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            method: 'POST',
            url: `**/${Cypress.env('apiPath')}/search/user`,
        }).as('loadUser');

        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer',
            },
            {
                key: 'media',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-users-permissions-user-listing')
            .should('be.visible');

        cy.get('.sw-users-permissions-user-listing__toolbar .sw-simple-search-field input')
            .clearTypeAndCheck('maxmuster');

        cy.wait('@loadUser').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--1')
            .should('not.exist');
        cy.contains('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username',
            'maxmuster');

        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username a')
            .click();

        cy.get('#sw-field--user-email')
            .should('be.visible')
            .should('have.value', 'max@muster.com');
    });

    it('@settings: edit user', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            method: 'POST',
            url: `**/${Cypress.env('apiPath')}/search/user`,
        }).as('loadUser');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
        }).as('oauthCall');

        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer',
            },
            {
                key: 'users_and_permissions',
                role: 'editor',
            },
            {
                key: 'media',
                role: 'viewer',
            },
            {
                key: 'media',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.wait('@loadUser').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-users-permissions-user-listing__toolbar .sw-simple-search-field input')
            .clearTypeAndCheck('maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--1')
            .should('not.exist');

        cy.contains('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username',
            'maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username a')
            .click();


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
            .typeAndCheck('Passw0rd!');

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

    it('@settings: edit user role', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            method: 'POST',
            url: `**/${Cypress.env('apiPath')}/search/user`,
        }).as('loadUser');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
        }).as('oauthCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/acl-role/*`,
            method: 'PATCH',
        }).as('saveRole');

        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer',
            },
            {
                key: 'users_and_permissions',
                role: 'editor',
            },
            {
                key: 'media',
                role: 'viewer',
            },
            {
                key: 'media',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.wait('@loadUser').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-users-permissions-user-listing__toolbar .sw-simple-search-field input')
            .clearTypeAndCheck('maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--1')
            .should('not.exist');

        cy.get('.sw-users-permissions-role-listing .sw-data-grid__row--0 .sw-data-grid__cell--name a')
            .click();


        cy.get('#sw-field--role-description')
            .should('be.visible')
            .clearTypeAndCheck('This is a description');

        cy.get('.sw-users-permissions-role-detail__button-save')
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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').its('response.statusCode').should('equal', 200);

        cy.wait('@saveRole').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal')
            .should('not.exist');

        cy.get('#sw-field--role-description')
            .should('be.visible')
            .should('have.value', 'This is a description');
    });

    it('@settings: create user', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        // Requests we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user`,
            method: 'POST',
        }).as('searchCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user`,
            method: 'POST',
        }).as('createCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/oauth/token`,
            method: 'POST',
        }).as('oauthCall');

        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer',
            },
            {
                key: 'users_and_permissions',
                role: 'editor',
            },
            {
                key: 'users_and_permissions',
                role: 'creator',
            },
            {
                key: 'media',
                role: 'viewer',
            },
            {
                key: 'media',
                role: 'editor',
            },
            {
                key: 'media',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').its('response.statusCode').should('equal', 200);

        cy.wait('@createCall').its('response.statusCode').should('equal', 204);
    });

    it('@settings: create user and delete them', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer',
            },
            {
                key: 'users_and_permissions',
                role: 'editor',
            },
            {
                key: 'users_and_permissions',
                role: 'creator',
            },
            {
                key: 'users_and_permissions',
                role: 'deleter',
            },
            {
                key: 'media',
                role: 'viewer',
            },
            {
                key: 'media',
                role: 'editor',
            },
            {
                key: 'media',
                role: 'creator',
            },
            {
                key: 'media',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Requests we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user`,
            method: 'POST',
        }).as('searchCall');

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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').its('response.statusCode').should('equal', 200);

        cy.wait('@createCall').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal')
            .should('not.exist');

        // should be able to delete the user
        cy.get('a.smart-bar__back-btn').click();

        cy.clickContextMenuItem(
            '.sw-settings-user-list__user-delete-action',
            '.sw-context-button__button',
            '.sw-users-permissions-user-listing .sw-data-grid__row--0',
        );

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw-modal__title', 'Warning');

        cy.get('.sw-modal__footer > .sw-button--danger')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@deleteCall').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal')
            .should('not.exist');

        cy.awaitAndCheckNotification('User "Abraham Allison " deleted.');
    });
});
