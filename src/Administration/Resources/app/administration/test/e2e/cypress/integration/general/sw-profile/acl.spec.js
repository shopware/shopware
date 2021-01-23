// / <reference types="Cypress" />

describe('Review: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);
            });
    });

    it('@general: has no access to sw-profile module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/profile/index`);
        });

        // open review without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-review-list').should('not.exist');

        // see menu without review menu item
        cy.get('.sw-admin-menu__user-actions-toggle').should('be.visible');
        cy.get('.sw-admin-menu__profile-item').should('not.exist');
        cy.get('.sw-admin-menu__user-actions-toggle').click();
        cy.get('.sw-admin-menu__logout-action').should('be.visible');
        cy.get('.sw-admin-menu__profile-item').should('not.exist');
    });

    it('@general: can edit own user', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'user',
                role: 'update_profile'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/profile/index`);
        });

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
            .typeAndCheck('Passw0rd!');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('not.exist');

                cy.get('#sw-field--email')
                    .should('be.visible')
                    .should('have.value', 'changed@shopware.com');
            });
    });
});
