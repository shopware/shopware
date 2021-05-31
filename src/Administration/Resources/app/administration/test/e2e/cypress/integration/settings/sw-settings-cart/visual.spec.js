/// <reference types="Cypress" />

describe('Cart settings: Visual testing', () => {
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

    it('@visual: check appearance of cart settings module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.cart`,
            method: 'get'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/cart/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Cart');
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Cart settings] Detail', '.sw-settings-cart');
    });
});
