/// <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test saving and loading the analytics tab', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@general: open analytics tab', () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);
        cy.get('.sw-tabs-item').contains('Analytics').click();

        cy.get('.sw-sales-channel-detail-analytics__headline-text').should('exist');
    });

    it('@general: there\'s no analytics tab for non-storefront sales channels', () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Headless', 0);
        cy.get('.sw-tabs-item').contains('Analytics').should('not.exist');
    });

    it('@general: save analytics data', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'patch'
        }).as('saveData');

        page.openSalesChannel('Storefront', 1);
        cy.get('.sw-tabs-item').contains('Analytics').click();

        cy.get('input[name=trackingId]').typeAndCheck('Example analytics ID');
        cy.get('input[name=analyticsActive]').click();

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('input[name=trackingId]').should('have.value', 'Example analytics ID');
    });
});
