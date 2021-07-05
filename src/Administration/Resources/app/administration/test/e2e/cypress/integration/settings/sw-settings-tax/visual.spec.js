// / <reference types="Cypress" />

describe('Tax: Visual testing', () => {
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

    it('@base @visual: check appearance of tax module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/tax`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-tax').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-page__main-content').should('be.visible');

        cy.get('.sw-loader').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', 'Reduced rate');
        cy.takeSnapshot('[Tax] Listing', '.sw-settings-tax-list-grid');

        cy.contains('.sw-data-grid__cell--name', 'Reduced rate').should('be.visible');
        cy.contains('.sw-data-grid__cell--name a', 'Reduced rate').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-data-grid__cell--country-name').should('is.visible');
        cy.takeSnapshot('[Tax] Details', '.sw-settings-tax-detail');
    });
});
