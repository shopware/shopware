/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Country: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of country module', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/country`,
            method: 'POST',
        }).as('getData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/country/**/states`,
            method: 'POST',
        }).as('getStates');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-settings-country').click();

        // Ensure snapshot consistency
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Country] Listing', '.sw-settings-country-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains('.sw-data-grid__cell--name a', 'Afghanistan').click();

        // Ensure snapshot consistency
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('input[name="sw-field--country-name"]').should('not.have.value', '');
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        // Take Snapshot
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Country] Detail', '.sw-settings-country-general__options-container', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-loader').should('not.exist');

        cy.get('input[name="sw-field--country-customerTax-enabled"]').should('exist');
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
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Country] Currency dependent modal', '.sw-settings-country-currency-dependent-modal', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
