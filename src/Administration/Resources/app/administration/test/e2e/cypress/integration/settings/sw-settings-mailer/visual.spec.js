// / <reference types="Cypress" />

describe('Mailer: Visual testing', () => {
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

    it('@base @visual: check appearance of mailer module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config?domain=core.mailerSettings`,
            method: 'get'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-settings__tab-system').click();
        cy.get('.sw-settings__tab-system.sw-tabs-item--active').should('exist');
        cy.get('#sw-settings__content-grid-system').should('be.visible');
        cy.get('#sw-settings-mailer').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings-mailer__radio-selection select').select('SMTP server');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings-mailer__input-fields').should('be.visible');
        cy.takeSnapshot('[Mailer] Details', '.sw-settings-mailer__input-fields');
    });
});
