// / <reference types="Cypress" />
/**
 * @package inventory
 */
describe('Number range: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of number ranges module', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/number-range`,
            method: 'POST',
        }).as('getData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/number-range-type`,
            method: 'POST',
        }).as('getRangeType');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-number-range').click();

        // Ensure snapshot consistency
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Number range] Listing', '.sw-settings-number-range-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--name a', 'Delivery notes').click();

        // Ensure snapshot consistency
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('#sw-field--preview').should('not.have.value', '');
        cy.get('#sw-field--state').should('not.have.value', '1');
        cy.wait('@getRangeType').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader--element').should('not.exist');
        cy.contains('.sw-button-process', 'Save').should('be.visible');

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Number range] Details', '.sw-settings-number-range-detail-assignment', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
