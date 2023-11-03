// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test acl', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.openInitialPage(Cypress.env('admin'));
    });

    it('@base @general: read sales channel',  { tags: ['pa-sales-channels'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer',
            },
        ]);

        cy.get('.sw-admin-menu__sales-channel-item--1').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-field--salesChannel-name input').should('have.value', 'Storefront');

        cy.get('.sw-tabs-item').eq(1).click();
        cy.get('.sw-sales-channel-detail-products').should('be.visible');

        cy.get('.sw-tabs-item').eq(2).click();
        cy.contains('.sw-sales-channel-detail-theme__info-name', 'Shopware default theme');

        cy.get('.sw-tabs-item').eq(3).click();
        cy.get('#trackingId').should('be.visible');
    });

    it('@general: edit sales channel',  { tags: ['pa-sales-channels'] },  () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer',
            },
            {
                key: 'sales_channel',
                role: 'editor',
            },
        ]);

        cy.get('.sw-admin-menu__sales-channel-item--1').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-field--salesChannel-name input').should('have.value', 'Storefront');
        cy.get('.sw-field--salesChannel-name input').clearTypeAndCheck('Shopsite');

        cy.get('.sw-sales-channel-detail__save-action').click();
        cy.contains('.sw-admin-menu__sales-channel-item--1', 'Shopsite');
    });

    it('@general: create sales channel',  { tags: ['pa-sales-channels'] },  () => {
        const page = new SalesChannelPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer',
            },
            {
                key: 'sales_channel',
                role: 'editor',
            },
            {
                key: 'sales_channel',
                role: 'creator',
            },
        ]);

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/sales-channel`,
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
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify creation
        cy.get(page.elements.salesChannelNameInput).should('have.value', '1st Epic Sales Channel');
    });

    it('@general: delete sales channel',  { tags: ['pa-sales-channels'] },  () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer',
            },
            {
                key: 'sales_channel',
                role: 'editor',
            },
            {
                key: 'sales_channel',
                role: 'deleter',
            },
        ]);

        cy.get('.sw-admin-menu__sales-channel-item--1').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-sales-channel-detail-base__button-delete').scrollIntoView().click();
        cy.get('.sw-modal__footer .sw-button--danger').click();

        cy.contains('.sw-admin-menu__sales-channel-item--0', 'Headless');
        cy.get('.sw-admin-menu__sales-channel-item--1').should('not.exist');
    });
});

