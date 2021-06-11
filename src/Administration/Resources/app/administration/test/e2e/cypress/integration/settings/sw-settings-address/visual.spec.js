describe('Address settings: Visual testing', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of address settings module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.address`,
            method: 'get'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('a[href="#/sw/settings/address/index"]').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-card__title').contains('Address');
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Address settings] Details', '.sw-settings-address');
    });
});
