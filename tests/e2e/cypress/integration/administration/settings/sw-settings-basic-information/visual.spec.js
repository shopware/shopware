// / <reference types="Cypress" />

describe('Basic information: Visual testing', () => {
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

    it('@visual: check appearance of basic information module', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.basicInformation`,
            method: 'GET'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/basic/information/index"]').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.contains('.sw-card__title', 'Basic information');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Basic information] Details', '.sw-settings-basic-information', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
