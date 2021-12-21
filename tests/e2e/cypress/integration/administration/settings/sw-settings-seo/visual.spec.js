// / <reference types="Cypress" />

describe('SEO: Visual testing', () => {
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

    it('@visual: check appearance of seo module', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/seo-url-template`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-seo').click();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.takeSnapshot('[SEO] Details', '.sw-seo-url-template-card');
    });
});
