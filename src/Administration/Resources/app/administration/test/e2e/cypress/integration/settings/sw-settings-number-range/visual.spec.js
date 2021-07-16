// / <reference types="Cypress" />

describe('Number range: Visual testing', () => {
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

    it('@visual: check appearance of number ranges module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/number-range`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-number-range').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Number range] Listing', '.sw-settings-number-range-list-grid');

        cy.contains('.sw-data-grid__cell--name a', 'Delivery notes').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('#sw-field--preview').should('not.have.value', '');

        cy.takeSnapshot('[Number range] Details', '.sw-number_range-quickinfo__alert-global-type');
    });
});
