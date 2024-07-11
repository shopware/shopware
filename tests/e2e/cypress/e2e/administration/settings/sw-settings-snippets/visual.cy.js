// / <reference types="Cypress" />

describe('Snippets: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createSnippetFixture()
            .then(() => {
                cy.fixture('snippet').as('testSnippet');
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of snippet module', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/snippet-set`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-snippet').click();

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-grid').should('be.visible');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-settings-snippet-set-file__column-changed-at .sw-grid__cell-content div',
            'color: #fff',
        );
        cy.get('.sw-settings-snippet-set-file__column-changed-at .sw-grid__cell-content div')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Snippets] Listing of snippet sets',
            '.sw-settings-snippet-set-list',
            null,
            {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-grid__cell-content a', 'BASE de-DE').click();

        // Ensure snapshot consistency
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.changeElementStyling('.sw-page__smart-bar-amount', 'color : #fff');

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Snippets] Snippet listing itself',
            '.sw-settings-snippet-list__grid',
            null,
            {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell-content a', 'aWonderful.customSnip').click();
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Snippets] Detail',
            '.sw-settings-snippet-detail',
            null,
            {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
