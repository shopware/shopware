// / <reference types="Cypress" />

describe('Delivery times: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of delivery time module', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/delivery-time`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-delivery-time').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', '1-2 weeks');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Delivery times] Listing', '.sw-settings-delivery-time-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
        cy.contains('.sw-data-grid__cell--name a', '1-3 days').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Delivery times] Details', '.sw-settings-delivery-time-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
