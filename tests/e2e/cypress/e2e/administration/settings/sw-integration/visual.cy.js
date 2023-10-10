/// <reference types="Cypress" />

describe('Integration: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createDefaultFixture('integration', { admin: true })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of integrations module', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/integration`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/integration/index"]').should('be.visible');
        cy.get('a[href="#/sw/integration/index"]').click();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Integration] Listing', '.sw-integration-list__overview');

        cy.contains('.sw-data-grid__cell-content a', 'chat-key').click();
        cy.handleModalSnapshot('Edit: chat-key');

        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Integration] Detail', '#sw-field--currentIntegration-label', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
