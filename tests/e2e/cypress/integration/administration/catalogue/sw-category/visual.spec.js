// / <reference types="Cypress" />

describe('Category: Visual tests', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic category workflow', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/category/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-category'
        });

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-category-tree').should('be.visible');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.get('.sw-skeleton__tree-item').should('not.exist');
        cy.get('.sw-skeleton__tree-item-nested').should('not.exist');
        cy.takeSnapshot('[Category] Detail', '.sw-category-tree');

        cy.contains('.tree-link', 'Home').click();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton__detail-bold').should('not.exist');
        cy.get('.sw-skeleton__detail').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-category-entry-point-card__navigation-list .sw-category-entry-point-card__navigation-entry',
            'color: #fff'
        );
        cy.get('.sw-category-entry-point-card__navigation-list .sw-category-entry-point-card__navigation-entry')
            .should('have.css', 'color', 'rgb(255, 255, 255)');
        cy.takeSnapshot('[Category] Listing', '.sw-card');

        cy.contains('.sw-category-detail__tab-products', 'Products').click();
        cy.get('.sw-skeleton__tree-item').should('not.exist');
        cy.get('.sw-skeleton__tree-item-nested').should('not.exist');

        cy.get('.sw-many-to-many-assignment-card__select-container').should('be.visible');
        cy.takeSnapshot('[Category] Detail, Products', '.sw-card');

        cy.get('.sw-tree-item__actions .sw-context-button')
            .click();

        cy.get('.sw-context-menu')
            .should('be.visible');

        cy.takeSnapshot('[Category] Detail, Open context menu', '.sw-page');
    });
});
