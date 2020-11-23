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
            url: '/api/v*/search/category',
            method: 'POST'
        }).as('dataRequest');

        cy.get('.sw-tree-item__label').first().click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');

        cy.wait('@dataRequest').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Take snapshot for visual testing
        cy.changeElementStyling(
            '.sw-category-sales-channel-card__list',
            'visibility: hidden'
        );
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('Category - detail', '.sw-category-detail-base');
    });
});
