// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test crud operations', () => {
    beforeEach(() => {
        cy.openInitialPage(Cypress.env('admin'));
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@base @general: create and read sales channel', { tags: ['pa-sales-channels'] }, () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/**/${Cypress.env('apiPath')}/sales-channel`,
            method: 'POST',
        }).as('saveData');

        // Open sales channel creation
        cy.contains('.sw-admin-menu__headline', 'Sales Channel');

        cy.get('.sw-admin-menu__headline-action').click();

        cy.contains('.sw-sales-channel-modal .sw-modal__title', 'Add Sales Channel');
        cy.get(`${page.elements.gridRow}--0 .sw-sales-channel-modal-grid__item-name`).click();
        cy.contains('.sw-sales-channel-modal .sw-modal__title', 'Storefront - details');
        cy.get('.sw-sales-channel-modal__add-sales-channel-action').click();

        // Fill in form and save new sales channel
        page.fillInBasicSalesChannelData('1st Epic Sales Channel');

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Verify creation
        cy.get('.sw-loader').should('not.exist');
        cy.get(page.elements.salesChannelNameInput).should('have.value', '1st Epic Sales Channel');
        cy.get('.sw-admin-menu__sales-channel-item').should('have.length', 3);
        cy.contains('.sw-admin-menu__sales-channel-item--0', '1st Epic Sales Channel');

        // Check if the sales channel can be used in other modules
        cy.clickMainMenuItem({
            targetPath: '#/sw/customer/index',
            mainMenuId: 'sw-customer',
            subMenuId: 'sw-customer-index',
        });
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.smart-bar__actions a[href="#/sw/customer/create"]').click();
        cy.get('.sw-customer-base-form__sales-channel-select')
            .typeSingleSelectAndCheck('1st Epic Sales Channel', '.sw-customer-base-form__sales-channel-select');
    });

    it('@base @general: update and read sales channel', { tags: ['pa-sales-channels'] }, () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'PATCH',
        }).as('saveData');

        // Edit and verify change in sales channel
        page.openSalesChannel('Storefront', 1);
        cy.get(page.elements.salesChannelNameInput).clear();
        cy.get(page.elements.salesChannelNameInput).type('Channel No 9');
        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.contains('Channel No 9');
    });

    it('@base @general: delete sales channel', { tags: ['pa-sales-channels'] }, () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/sales-channel/*`,
            method: 'delete',
        }).as('deleteData');

        // Delete sales channel
        page.openSalesChannel('Headless');
        page.deleteSingleSalesChannel('Headless');
        cy.wait('@deleteData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-admin-menu__sales-channel-item--1').should('not.exist');
    });
});

