// / <reference types="Cypress" />

describe('Tax: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @visual: check appearance of tax module', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/tax`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-tax').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-page__main-content').should('be.visible');

        cy.get('.sw-loader').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', 'Reduced rate');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Tax] Listing', '.sw-settings-tax-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--name', 'Reduced rate').should('be.visible');
        cy.contains('.sw-data-grid__cell--name a', 'Reduced rate').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-data-grid__cell--country-name').should('is.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Tax] Details', '.sw-settings-tax-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
