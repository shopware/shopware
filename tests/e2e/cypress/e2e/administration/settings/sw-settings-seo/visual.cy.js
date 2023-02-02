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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of seo module', { tags: ['pa-sales-channels'] }, () => {
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
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[SEO] Details', '.sw-seo-url-template-card', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
