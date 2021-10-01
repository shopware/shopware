// / <reference types="Cypress" />

describe('Scale units: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('unit');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@visual: check appearance of scale unit module', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/unit`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-units').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.contains('.sw-data-grid__cell--name', 'Gramm').should('be.visible');
        cy.takeSnapshot('[Unit] Listing', '.sw-card-view__content');
    });
});

