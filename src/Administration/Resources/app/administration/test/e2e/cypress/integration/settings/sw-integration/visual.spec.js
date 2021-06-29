// / <reference types="Cypress" />

describe('Integration: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('integration', { admin: true });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@visual: check appearance of integrations module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/integration`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');

        cy.get('a[href="#/sw/integration/index"]').should('be.visible');
        cy.get('a[href="#/sw/integration/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Integration] Listing', '.sw-integration-list__overview');

        cy.contains('.sw-data-grid__cell-content a', 'chat-key').click();
        cy.handleModalSnapshot('Edit: chat-key');

        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Integration] Detail', '#sw-field--currentIntegration-label');
    });
});
