// / <reference types="Cypress" />

describe('Country: Visual testing', () => {
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

    it('@visual: check appearance of country module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/country`,
            method: 'post'
        }).as('getData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/country/**/states`,
            method: 'post'
        }).as('getStates');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-country').click();

        // Ensure snapshot consistency
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // Take Snapshot
        cy.takeSnapshot('[Country] Listing', '.sw-settings-country-list-grid');

        cy.contains('.sw-data-grid__cell--name a', 'Afghanistan').click();

        // Ensure snapshot consistency
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('input[name="sw-field--country-name"]').should('not.have.value', '');
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Take Snapshot
        cy.takeSnapshot('[Country] Detail', '.sw-settings-country-general__options-container');

        cy.get('.sw-loader').should('not.exist');

        cy.get('input[name="sw-field--country-customerTax-enabled"]').should('be.visible');
        cy.get('input[name="sw-field--country-customerTax-enabled"]').check().then(() => {
            cy.get('.sw-settings-country-general-customer-tax').should('be.visible');
            cy.get('input[name=sw-field--country-customerTax-amount]').should('be.visible');
        });

        cy.get('input[name=sw-field--country-customerTax-amount]').type('300');
        cy.get('.sw-settings-country-general__currency-dependent-modal').should('be.visible');
        cy.get('.sw-settings-country-general__currency-dependent-modal').click({ force: true }).then(() => {
            cy.get('.sw-settings-country-currency-dependent-modal').should('be.visible');
        });

        cy.handleModalSnapshot('Currency dependent values');
        cy.get('.sw-settings-country-currency-dependent-modal').should('be.visible');
        cy.takeSnapshot('[Country] Currency dependent modal', '.sw-settings-country-currency-dependent-modal');
    });
});
