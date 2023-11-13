// / <reference types="Cypress" />
/**
 * @package inventory
 */
describe('Administration: Check module navigation', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createReviewFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @navigation: navigate to review module', { tags: ['pa-content-management'] }, () => {
        // Open reviews
        cy.clickMainMenuItem({
            targetPath: '#/sw/review/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-review',
        });
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
