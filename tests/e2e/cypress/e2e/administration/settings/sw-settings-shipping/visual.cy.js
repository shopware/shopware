// / <reference types="Cypress" />

describe('Administration: Check module navigation in settings', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @visual: check appearance of shipping module', { tags: ['pa-checkout', 'VUE3'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/shipping-method`,
            method: 'POST',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-shipping').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-settings-shipping-list__content').should('exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.log('change Sorting direction from None to ASC');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');

        cy.sortAndCheckListingAscViaColumn('Name', 'Express');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Shipping] Listing', '.sw-settings-shipping-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--name a', 'Express').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-media-upload-v2__header .sw-context-button__button').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Shipping] Details', '.sw-card__content', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
