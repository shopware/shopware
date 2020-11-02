// / <reference types="Cypress" />

describe('User: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: view user', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);
        cy.get('.sw-users-permissions-user-listing')
            .should('be.visible');

        cy.get('.sw-users-permissions-user-listing__toolbar .sw-simple-search-field input')
            .clearTypeAndCheck('maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--1')
            .should('not.exist');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username')
            .contains('maxmuster');

        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username a')
            .click();

        cy.get('#sw-field--user-email')
            .should('be.visible')
            .should('have.value', 'max@muster.com');
    });

    it('@settings: edit user', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer'
            },
            {
                key: 'users_and_permissions',
                role: 'editor'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);

        cy.get('.sw-users-permissions-user-listing__toolbar .sw-simple-search-field input')
            .clearTypeAndCheck('maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--1')
            .should('not.exist');

        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username')
            .contains('maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--0 .sw-data-grid__cell--username a')
            .click();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/oauth/token',
            method: 'post'
        }).as('oauthCall');

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
            .typeAndCheck('Passw0rd!');

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

    it('@settings: edit user role', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer'
            },
            {
                key: 'users_and_permissions',
                role: 'editor'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);

        cy.get('.sw-users-permissions-user-listing__toolbar .sw-simple-search-field input')
            .clearTypeAndCheck('maxmuster');
        cy.get('.sw-users-permissions-user-listing .sw-data-grid__row--1')
            .should('not.exist');

        cy.get('.sw-card.sw-users-permissions-role-listing .sw-data-grid__row--0 .sw-data-grid__cell--name a')
            .click();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/oauth/token',
            method: 'post'
        }).as('oauthCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/acl-role/*`,
            method: 'patch'
        }).as('saveRole');

        cy.get('#sw-field--role-description')
            .should('be.visible')
            .clearTypeAndCheck('This is a description');

        cy.get('.sw-users-permissions-role-detail__button-save')
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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@saveRole').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-modal')
            .should('not.be.visible');

        cy.get('#sw-field--role-description')
            .should('be.visible')
            .should('have.value', 'This is a description');
    });

    it('@settings: create user', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer'
            },
            {
                key: 'users_and_permissions',
                role: 'editor'
            },
            {
                key: 'users_and_permissions',
                role: 'creator'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);


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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@oauthCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@createCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@settings: create user and delete them', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'users_and_permissions',
                role: 'viewer'
            },
            {
                key: 'users_and_permissions',
                role: 'editor'
            },
            {
                key: 'users_and_permissions',
                role: 'creator'
            },
            {
                key: 'users_and_permissions',
                role: 'deleter'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/users/permissions/index`);

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
            .typeAndCheck('Passw0rd!');

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
            '.sw-users-permissions-user-listing .sw-data-grid__row--0'
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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.wait('@deleteCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-modal')
            .should('not.be.visible');

        cy.awaitAndCheckNotification('User "Abraham Allison " has been deleted.');
    });
});
