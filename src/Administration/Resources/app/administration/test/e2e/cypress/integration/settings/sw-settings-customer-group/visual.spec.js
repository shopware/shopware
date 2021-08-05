// / <reference types="Cypress" />

describe('Customer group: Visual testing', () => {
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

    it('@visual: check appearance of customer group module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer-group`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-customer-group').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-customer-group-list-grid').should('be.visible');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Customer group] Listing', '.sw-settings-customer-group-list');

        cy.contains('.sw-data-grid__cell--name a', 'Standard customer group').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Customer group] Details', '.sw-settings-customer-group-detail');
    });
});
