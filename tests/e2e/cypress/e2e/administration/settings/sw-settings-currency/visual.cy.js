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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of  currency module', { tags: ['pa-system-settings'] }, () => {
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

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Short name', 'CHF');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Currency] Listing', '.sw-settings-currency-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--name a', 'Czech').click();
        cy.get('.sw-page__main-content').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Currency] Detail', '.sw-settings-currency-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
