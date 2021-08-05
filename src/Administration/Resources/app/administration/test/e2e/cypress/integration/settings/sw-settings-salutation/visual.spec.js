// / <reference types="Cypress" />

describe('Salutation: Visual tesing', () => {
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

    it('@visual: check appearance of salutation module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/salutation`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-salutation').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Salutation] Listing', '.sw-settings-salutation-list-grid');

        cy.contains('.sw-data-grid__cell--salutationKey', 'mr').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Salutation] Details', '.sw-card__content');
    });
});
