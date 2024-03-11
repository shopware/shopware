// / <reference types="Cypress" />

describe('Review: Test ACL privileges', () => {
    beforeEach(() => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_info/config`,
            method: 'GET',
        }).as('infoCall');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);

        cy.wait('@infoCall');

        cy.contains('.smart-bar__header', 'Your profile');
        cy.contains('.sw-card__title', 'Profile information');
        cy.get('#sw-field--user-username').should('be.visible');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@general: has no access to sw-profile module', { tags: ['pa-system-settings'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/profile/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open review without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.contains('h1', 'Access denied');
        cy.get('.sw-review-list').should('not.exist');

        // see menu without review menu item
        cy.get('.sw-admin-menu__user-actions-toggle').should('be.visible');
        cy.get('.sw-admin-menu__profile-item').should('not.exist');
        cy.get('.sw-admin-menu__user-actions-toggle').click();
        cy.get('.sw-admin-menu__logout-action').should('be.visible');
        cy.get('.sw-admin-menu__profile-item').should('not.exist');
    });

    it('@general: can edit own user',  { tags: ['pa-system-settings'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'user',
                role: 'update_profile',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/profile/index`);

            cy.contains('.smart-bar__header', 'Your profile');
            cy.contains('.sw-card__title', 'Profile information');
            cy.get('#sw-field--user-username').should('be.visible');
            cy.get('.sw-loader').should('not.exist');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
        cy.get('#sw-field--user-email').should('be.visible');
        cy.get('#sw-field--user-email').scrollIntoView();
        if(Cypress.isBrowser('chrome')) {
            cy.get('#sw-field--user-email').realHover();
        }
        cy.get('#sw-field--user-email').click();
        cy.get('#sw-field--user-email').clear();
        cy.get('#sw-field--user-email').type('changed@shopware.com');

        cy.get('.sw-profile__save-action')
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
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('not.exist');

                cy.get('#sw-field--user-email')
                    .should('be.visible')
                    .should('have.value', 'changed@shopware.com');
            });
    });
});
