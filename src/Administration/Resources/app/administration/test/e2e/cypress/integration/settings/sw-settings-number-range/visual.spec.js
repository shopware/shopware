// / <reference types="Cypress" />

describe('Number range: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of number ranges module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/number-range`,
            method: 'post'
        }).as('getData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/number-range-type`,
            method: 'post'
        }).as('getRangeType');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-number-range').click();

        // Ensure snapshot consistency
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // Take Snapshot
        cy.takeSnapshot('[Number range] Listing', '.sw-settings-number-range-list-grid');

        cy.contains('.sw-data-grid__cell--name a', 'Delivery notes').click();

        // Ensure snapshot consistency
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('#sw-field--preview').should('not.have.value', '');
        cy.get('#sw-field--state').should('not.have.value', '1');
        cy.wait('@getRangeType').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-loader').should('not.exist');
            cy.get('.sw-loader--element').should('not.exist');
            cy.contains('.sw-button-process', 'Save').should('be.visible');
        });

        // Take Snapshot
        cy.takeSnapshot('[Number range] Details', '.sw-number_range-quickinfo__alert-global-type');
    });
});
