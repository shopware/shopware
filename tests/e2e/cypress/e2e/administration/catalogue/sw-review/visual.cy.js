/**
 * @package content
 */
// / <reference types="Cypress" />

describe('Administration: Check module navigation', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.createReviewFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @navigation: navigate to review module', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/product-review`,
            method: 'POST'
        }).as('getData');

        // Open reviews
        cy.clickMainMenuItem({
            targetPath: '#/sw/review/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-review'
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-review-list').should('be.visible');

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.contains('.sw-data-grid__cell--title', 'Bestes Produkt').should('be.visible');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-data-grid__cell--createdAt .sw-data-grid__cell-content', '01 Jan 2018, 00:00');

        // Take snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Review] Listing', '.sw-review-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        // Open detail review
        cy.get('.sw-review-list').should('be.visible');
        cy.contains('.sw-data-grid__cell--title', 'Bestes Produkt').click();
        cy.get('.sw-loader').should('not.exist');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText(':nth-child(1) > :nth-child(1) > dd', '01 Jan 2018, 00:00');

        // Take snapshot
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Review] Details', '.sw-card-section--secondary', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
