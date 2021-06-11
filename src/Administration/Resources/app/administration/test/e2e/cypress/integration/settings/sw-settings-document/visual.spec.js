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
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/document-base-config`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/document/index').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.takeSnapshot('[Documents] Listing', '.sw-settings-document-list-grid');

        cy.contains('.sw-document-list__column-name a', 'credit_note').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Documents] Details', '.sw-settings-document-detail');
        cy.get('.sw-settings-document-detail').should('be.visible');
    });
});
