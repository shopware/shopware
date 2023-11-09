// / <reference types="Cypress" />

describe('Payment: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @navigation: navigate to payment module', { tags: ['pa-checkout', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/payment-method`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-payment').click();

        // Ensure snapshot consistency
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Payment] Overview', '.sw-settings-payment-overview', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-card', 'Cash on delivery')
            .find('.sw-internal-link')
            .click();

        // Ensure snapshot consistency
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-media-upload-v2__header .sw-context-button__button').should('be.visible');
        cy.get('.sw-settings-payment-detail__condition_container').should('be.visible');

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Payment] Details', '.sw-settings-payment-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
