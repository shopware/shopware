// / <reference types="Cypress" />

describe('Currency: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of  currency module', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/currency`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-currency').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Short name', 'CHF');
        cy.takeSnapshot('[Currency] Listing', '.sw-settings-currency-list-grid');

        cy.contains('.sw-data-grid__cell--name a', 'Czech').click();
        cy.get('.sw-page__main-content').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.takeSnapshot('[Currency] Detail', '.sw-settings-currency-detail');
    });
});
