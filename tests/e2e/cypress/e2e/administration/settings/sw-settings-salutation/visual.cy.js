/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Salutation: Visual tesing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of salutation module', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/salutation`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-salutation').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Salutation] Listing', '.sw-settings-salutation-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--salutationKey', 'mr').click();
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Salutation] Details', '.sw-card__content', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
