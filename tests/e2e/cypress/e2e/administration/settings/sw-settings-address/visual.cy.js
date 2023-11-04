describe('Address settings: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of address settings module', { tags: ['pa-customers-orders'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.address`,
            method: 'GET',
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('a[href="#/sw/settings/address/index"]').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-card__title', 'Address');
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Address settings] Details', '.sw-settings-address', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
