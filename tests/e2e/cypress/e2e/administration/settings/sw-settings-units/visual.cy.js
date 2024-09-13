// / <reference types="Cypress" />
/**
 * @package inventory
 */
describe('Scale units: Visual testing', () => {
    beforeEach(() => {
        cy.createDefaultFixture('unit')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of scale unit module', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/unit`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-units').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-data-grid__cell--name', 'Gramm').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Unit] Listing', '.sw-card-view__content', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});

