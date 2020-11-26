/// <reference types="Cypress" />

describe('Theme: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialStateVisual()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
            });
    });

    it('@visual: check appearance of basic theme workflow', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();
        cy.get('.sw-colorpicker').should('be.visible');

        // Take snapshot for visual testing
        cy.changeElementStyling(
            ':nth-child(2) > .sw-theme-manager-detail__saleschannel-link > span',
            'color: #fff'
        );
        cy.changeElementStyling(
            ':nth-child(3) > .sw-theme-manager-detail__saleschannel-link > span',
            'color: #fff'
        );
        cy.takeSnapshot('Theme detail', '.sw-theme-manager-detail__area');
    });
});
