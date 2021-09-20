// / <reference types="Cypress" />

describe('Language: Visual testing', () => {
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

    it('@base @visual: check appearance of language module', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-language').click();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Language] Listing', '.sw-settings-language-list');

        cy.contains('.sw-data-grid__cell--name a', 'English').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Language] Details', '.sw-settings-language-detail');
    });
});
