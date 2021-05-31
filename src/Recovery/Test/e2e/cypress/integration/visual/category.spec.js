/// <reference types="Cypress" />

describe('Category: Visual tests', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.setLocaleToEnGb()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@visual: check appearance of basic category workflow', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/search/category',
            method: 'POST'
        }).as('dataRequest');

        cy.get('.sw-tree-item__label').first().click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Change visibility of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-category-entry-point-card__navigation-list',
            'visibility: hidden'
        );
        cy.prepareAdminForScreenshot();

        // Take snapshot for visual testing
        cy.takeSnapshot('Category - detail', '.sw-category-detail-base');
    });
});
