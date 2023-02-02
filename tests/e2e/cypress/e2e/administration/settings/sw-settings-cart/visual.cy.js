// / <reference types="Cypress" />

describe('Cart settings: Visual testing', () => {
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

    it('@visual: check appearance of cart settings module', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.cart`,
            method: 'GET'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/cart/index"]').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.contains('.sw-card__title', 'Cart');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Cart settings] Detail', '.sw-settings-cart', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
