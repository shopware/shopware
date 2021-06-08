/// <reference types="Cypress" />

describe('Cache: Visual testing', () => {
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

    it('@visual: check appearance of country module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/cache_info`,
            method: 'get'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');
        cy.get('#sw-settings-cache').click();

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Cache] Detail', '.sw-settings-cache');
    });
});
