// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test saving and loading the analytics tab', () => {
    beforeEach(() => {
        cy.openInitialPage(Cypress.env('admin'));
    });

    it('@general: open analytics tab', { tags: ['pa-sales-channels', 'jest'] }, () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);
        cy.contains('.sw-tabs-item', 'Analytics').click();

        cy.get('.sw-sales-channel-detail-analytics__headline-text').should('exist');
    });

    it('@general: there\'s no analytics tab for non-storefront sales channels', { tags: ['pa-sales-channels', 'jest'] }, () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Headless', 0);
        cy.contains('.sw-tabs-item', 'Analytics').should('not.exist');
    });

    it('@general: save analytics data', { tags: ['pa-sales-channels'] }, () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.intercept({
            method: 'PATCH',
            url: `${Cypress.env('apiPath')}/sales-channel/*`,
        }).as('saveData');

        page.openSalesChannel('Storefront', 1);
        cy.contains('.sw-tabs-item', 'Analytics').click();

        cy.get('input[name=trackingId]').typeAndCheck('Example analytics ID');
        cy.get('input[name=analyticsActive]').click();

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get('input[name=trackingId]').should('have.value', 'Example analytics ID');
    });
});
