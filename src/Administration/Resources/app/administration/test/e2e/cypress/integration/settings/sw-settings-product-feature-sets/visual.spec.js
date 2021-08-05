// / <reference types="Cypress" />

describe('Feature sets: Visual testing', () => {
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

    it('@visual: check appearance of delivery time module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-feature-set`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-product-feature-sets').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.takeSnapshot('[Feature sets] Listing', '.sw-settings-product-feature-sets-list-grid');
        cy.contains('.sw-data-grid__cell--name a', 'Default').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');

        cy.takeSnapshot('[Feature sets] Details', '.sw-product-feature-set__toolbar');
    });
});
