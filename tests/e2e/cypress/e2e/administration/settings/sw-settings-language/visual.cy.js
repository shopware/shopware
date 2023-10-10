/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Language: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @visual: check appearance of language module', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-language').click();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Language] Listing', '.sw-settings-language-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--name a', 'English').click();
        cy.get('.sw-page__main-content').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Language] Details', '.sw-settings-language-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
