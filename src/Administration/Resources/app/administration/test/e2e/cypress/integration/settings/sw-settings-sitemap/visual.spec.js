// / <reference types="Cypress" />

describe('Sitemap: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of sitemap module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/schema?domain=core.sitemap`,
            method: 'get'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.contains('Sitemap').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-system-config').should('be.visible');
        cy.takeSnapshot('[Sitemap] Detail', '.sw-card__toolbar');
    });
});
