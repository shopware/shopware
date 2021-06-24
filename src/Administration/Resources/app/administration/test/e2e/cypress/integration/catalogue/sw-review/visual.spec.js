// / <reference types="Cypress" />

describe('Administration: Check module navigation', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createReviewFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @navigation: navigate to review module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-review`,
            method: 'post'
        }).as('getData');

        // Open reviews
        cy.clickMainMenuItem({
            targetPath: '#/sw/review/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-review'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-review-list').should('be.visible');

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.contains('.sw-data-grid__cell--title', 'Bestes Produkt').should('be.visible');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-data-grid__cell--createdAt',
            'color: #fff'
        );
        cy.get('.sw-data-grid__cell--createdAt')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Take snapshot
        cy.takeSnapshot('[Review] Listing', '.sw-review-list');

        // Open detail review
        cy.get('.sw-review-list').should('be.visible');
        cy.contains('.sw-data-grid__cell--title', 'Bestes Produkt').click();
        cy.get('.sw-loader').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            ':nth-child(1) > :nth-child(1) > dd',
            'color: #F6F6F6'
        );
        cy.get(':nth-child(1) > :nth-child(1) > dd')
            .should('have.css', 'color', 'rgb(246, 246, 246)');
        // Take snapshot
        cy.takeSnapshot('[Review] Listing', '.sw-card-section--secondary');
    });
});
