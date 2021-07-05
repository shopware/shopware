// / <reference types="Cypress" />

describe('Currency: Visual testing', () => {
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

    it('@visual: check appearance of  currency module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/currency`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-currency').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Short name', 'CHF');
        cy.takeSnapshot('[Currency] Listing', '.sw-settings-currency-list-grid');

        cy.contains('.sw-data-grid__cell--name a', 'Czech').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Currency] Detail', '.sw-settings-currency-detail');
    });
});
