// / <reference types="Cypress" />

describe('Theme: Test loading and saving of theme', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
            });
    });

    it('@base @content: opens and loads theme config', () => {
        cy.server();
        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area').its('length').should('be.gte', 1);
    });

    it('@base @content: saves theme config', () => {
        cy.server();
        cy.route({
            url: '/api/v*/_action/theme/*',
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area');

        cy.get('.sw-colorpicker .sw-colorpicker__input').first().clear().typeAndCheck('#000');

        cy.get('.smart-bar__actions .sw-button-process.sw-button--primary').click();
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.wait(200);
            cy.get('.sw-colorpicker .sw-colorpicker__input').first().should('have.value', '#000');
        });
    });
});
