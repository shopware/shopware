// / <reference types="Cypress" />

describe('Sales Channel: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                // freezes the system time to Jan 1, 2018
                const now = new Date(2018, 1, 1);
                cy.clock(now);
            })
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic sales channel workflow', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel`,
            method: 'post'
        }).as('saveData');

        // Open sales channel
        cy.contains('Storefront').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('#sw-field--salesChannel-name').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-sales-channel-detail__select-customer-group .sw-entity-single-select__selection')
            .contains('Standard customer group');
        cy.get('.sw-sales-channel-detail__assign-countries .sw-entity-single-select__selection')
            .contains('Germany');
        cy.get('.sw-sales-channel-detail__assign-languages .sw-entity-single-select__selection')
            .contains('English');

        // Change display of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-entity-multi-select .sw-select-selection-list',
            'display: none'
        );
        cy.get('.sw-entity-multi-select .sw-select-selection-list')
            .should('have.css', 'display', 'none');


        // Change background-color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-entity-multi-select .sw-select__selection',
            'background-color: #189EF'
        );

        cy.get('.sw-entity-multi-select .sw-select__selection')
            .should('have.css', 'color', 'rgb(82, 102, 122)');
        cy.takeSnapshot('[Sales channel] Detail', '.sw-sales-channel-detail-base');
    });
});

