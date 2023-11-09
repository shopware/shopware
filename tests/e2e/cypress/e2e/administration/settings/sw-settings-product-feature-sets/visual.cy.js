// / <reference types="Cypress" />

describe('Feature sets: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of product feature sets module',  { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-feature-set`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-product-feature-sets').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Feature sets] Listing', '.sw-settings-product-feature-sets-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
        cy.contains('.sw-data-grid__cell--name a', 'Default').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Feature sets] Details', '.sw-product-feature-set__toolbar', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
