/// <reference types="Cypress" />

describe('Sales Channel: Visual tests', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                // freezes the system time to Jan 1, 2018
                const now = new Date(2018, 1, 1);
                cy.clock(now);
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic sales channel workflow', { tags: ['pa-system-settings'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/sales-channel`,
            method: 'post',
        }).as('saveData');

        // Open sales channel
        const saleschannel = Cypress.env('testDataUsage') ? 'Footwear' : 'E2E install test';

        cy.contains(saleschannel).click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.sw-field--salesChannel-name').should('be.visible');

        // Ensure screenshot consistency
        const customerGroup = Cypress.env('locale') === 'en-GB' ? 'Standard customer group' : 'Standard-Kundengruppe';
        const country = Cypress.env('locale') === 'en-GB' ? 'United Kingdom' : 'Deutschland';
        const language = Cypress.env('locale') === 'en-GB' ? 'English' : 'Deutsch';
        cy.get('.sw-sales-channel-detail__select-customer-group .sw-entity-single-select__selection')
            .contains(customerGroup);
        cy.get('.sw-sales-channel-detail__assign-countries .sw-entity-single-select__selection')
            .contains(country);
        cy.get('.sw-sales-channel-detail__assign-languages .sw-entity-single-select__selection')
            .contains(language);

        // Change display of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-entity-multi-select .sw-select-selection-list',
            'display: none',
        );

        // Change background-color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-entity-multi-select .sw-select__selection',
            'background-color: #189EF',
        );

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Sales channel detail`, '.sw-sales-channel-detail-base');
    });
});

