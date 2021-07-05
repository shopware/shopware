// / <reference types="Cypress" />

describe('Administration: Check module navigation in settings', () => {
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

    it('@base @visual: check appearance of shipping module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/shipping-method`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-shipping').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-shipping-list__content').should('exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', 'Express');
        cy.takeSnapshot('[Shipping] Listing', '.sw-settings-shipping-list');

        cy.contains('.sw-data-grid__cell--name a', 'Express').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Shipping] Details', '.sw-card__content');
    });
});
