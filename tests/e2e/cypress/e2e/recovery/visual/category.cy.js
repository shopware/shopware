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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic category workflow', { tags: ['pa-system-settings'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: '/api/search/category',
            method: 'POST'
        }).as('dataRequest');

        cy.get('.sw-tree-item__label').first().click();

        cy.wait('@dataRequest').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton__detail-bold').should('not.exist');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');
        cy.get('.sw-media-upload-v2__switch-mode').should('exist');
        // Change visibility of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-category-entry-point-card__navigation-list',
            'visibility: hidden'
        );
        cy.prepareAdminForScreenshot();

        // Take snapshot for visual testing
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Category - detail`, '.sw-category-detail-base');
    });
});
