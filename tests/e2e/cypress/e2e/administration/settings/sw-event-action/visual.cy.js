// / <reference types="Cypress" />

/**
 * @deprecated tag:v6.5.0 - Can be removed as business events / event actions will be removed
 */
describe('Event actions: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.skipOnFeature('v6.5.0.0');

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

    it('@visual: @check appearance of event action workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/event-action`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-event-action').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Event action] Listing', '.sw-event-action-list__grid');
        cy.takeSnapshot('[Event action] Deprecated modal', '.sw-event-action-deprecated-modal');
        cy.takeSnapshot('[Event action] Deprecated alert', '.sw-event-action-deprecated-alert');
    });
});
