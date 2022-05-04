// / <reference types="Cypress" />

describe('Documents: Visual testing', () => {
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

    it('@visual: check appearance of document module', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/document-base-config`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/document/index').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Documents] Listing', '.sw-settings-document-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-document-list__column-name a', 'credit_note').click();
        cy.get('.sw-page__main-content').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Documents] Details', '.sw-settings-document-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
