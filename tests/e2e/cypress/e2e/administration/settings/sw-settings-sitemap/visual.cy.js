// / <reference types="Cypress" />

describe('Sitemap: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of sitemap module', { tags: ['pa-sales-channels', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.sitemap`,
            method: 'GET',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.contains('Sitemap').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-system-config').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Sitemap] Detail', '.sw-card__toolbar', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
