// / <reference types="Cypress" />

describe('Theme: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic theme workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/theme`,
            method: 'POST'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/theme/manager/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-theme-manager'
        });

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-theme-list__list').should('be.visible');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();
        cy.get('.sw-colorpicker').should('be.visible');
    });
});
