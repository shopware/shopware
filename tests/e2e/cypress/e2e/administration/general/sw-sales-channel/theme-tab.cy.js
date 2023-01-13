// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Tests about the theme tab in the sales channel', () => {
    beforeEach(() => {
        cy.openInitialPage(Cypress.env('admin'));
    });

    it('@general: open theme tab', { tags: ['pa-sales-channels', 'jest'] }, () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);
        cy.contains('.sw-tabs-item', 'Theme').click();

        cy.get('.sw-sales-channel-detail-theme').should('exist');
    });

    it('@general: theme tab has a title', { tags: ['pa-sales-channels', 'jest'] }, () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);

        cy.contains('.sw-tabs-item', 'Theme').click();
        cy.title().should('include', 'Storefront');
    });
});
