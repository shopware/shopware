/// <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Adding domains to a sales-channel', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/sales-channel-domain`,
            method: 'POST'
        }).as('verifyDomain');
    });

    it('@general: Domains are displayed', () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);

        cy.get('.sw-sales-channel-detail-domains').should('exist');
        cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body').find('.sw-data-grid__row').should('have.length', 1);
    });

    it('@general: Add new domain', () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);

        cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body').find('.sw-data-grid__row').should('have.length', 1);

        page.addExampleDomain();

        cy.wait('@verifyDomain');
        cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body').find('.sw-data-grid__row').should('have.length', 2);
    });

    it('@general: Can\'t add the same domain URL twice', () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);

        page.addExampleDomain();
        cy.wait('@verifyDomain').its('response.statusCode').should('equal', 200);

        page.addExampleDomain();
        cy.contains('.sw-block-field', 'Url').should('have.class', 'has--error');
    });

    it('@general: Can re-add a previously deleted domain', () => {
        const page = new SalesChannelPageObject();

        page.openSalesChannel('Storefront', 1);

        cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body').find('.sw-data-grid__row').should('have.length', 1);

        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell:first-child .sw-data-grid__cell-content').invoke('text').then((text) => {
            const url = text;

            cy.get('.sw-data-grid__row--0').find('.sw-context-button__button').click();
            cy.contains('.sw-context-menu-item', 'Delete domain').click();
            cy.contains('.sw-modal__dialog .sw-button--danger', 'Delete').click();

            cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body').find('.sw-data-grid__row').should('have.length', 0);

            page.addExampleDomain(false);

            cy.get('#sw-field--currentDomain-url').clear().type(url);
            cy.contains('.sw-button--primary', 'Add domain').click();

            cy.wait('@verifyDomain');
            cy.get('.sw-sales-channel-detail-domains .sw-data-grid__body').find('.sw-data-grid__row').should('have.length', 1);
        });
    });
});
