// / <reference types="Cypress" />

describe('Delivery times: Visual testing', () => {
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

    it('@visual: check appearance of delivery time module', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/delivery-time`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-delivery-time').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', '1-2 weeks');
        cy.takeSnapshot('[Delivery times] Listing', '.sw-settings-delivery-time-list-grid');
        cy.contains('.sw-data-grid__cell--name a', '1-3 days').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Delivery times] Details', '.sw-settings-delivery-time-detail');
    });
});
