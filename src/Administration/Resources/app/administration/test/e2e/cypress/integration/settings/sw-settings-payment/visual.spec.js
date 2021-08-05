// / <reference types="Cypress" />

describe('Payment: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @navigation: navigate to payment module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/payment-method`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-payment').click();

        // Ensure snapshot consistency
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // Take Snapshot
        cy.takeSnapshot('[Payment] Listing', '.sw-settings-payment-list');

        cy.contains('.sw-data-grid__cell--name a', 'Cash on delivery').click();

        // Ensure snapshot consistency
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-media-upload-v2__header .sw-context-button__button').should('be.visible');
        cy.get('.sw-settings-payment-detail__condition_container').should('be.visible');

        // Take Snapshot
        cy.takeSnapshot('[Payment] Details', '.sw-settings-payment-detail');
    });
});
