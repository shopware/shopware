/// <reference types="Cypress" />

describe('User: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState();
    });
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of user module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/user`,
            method: 'post'
        }).as('getData');

        // Set other role
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            }
        ]);

        // Log out and in as admin (again)
        cy.get('.sw-admin-menu__user-actions-toggle').click();
        cy.clearCookies();
        cy.reload().then(() => {
            cy.get('.sw-login__container').should('be.visible');

            // login
            cy.login('admin');
        });

        // Finally check user module
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.contains('Users & permissions').should('be.visible');
        cy.contains('Users & permissions').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-user-list').should('be.visible');

        // Shoot snapshots
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[User] Listing', '.sw-users-permissions-role-listing');

        cy.contains('.sw-data-grid__cell--username', 'admin').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[User] Details', '.sw-settings-user-detail');
    });
});
