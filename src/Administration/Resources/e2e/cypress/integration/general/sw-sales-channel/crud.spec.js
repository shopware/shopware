// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@package @general: create and read sales channel', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/sales-channel',
            method: 'post'
        }).as('saveData');

        // Open sales channel creation
        cy.get('.sw-admin-menu__headline').contains('Sales channel');

        cy.get('.sw-admin-menu__headline-action').click();
        cy.get('.sw-sales-channel-modal__title').contains('Add sales channel');
        cy.get(`${page.elements.gridRow}--0 .sw-sales-channel-modal__show-detail-action`).click();
        cy.get('.sw-sales-channel-modal__title').contains('Details of Storefront');
        cy.get('.sw-sales-channel-modal__add-sales-channel-action').click();

        // Fill in form and save new sales channel
        page.fillInBasicSalesChannelData('1st Epic Sales Channel');

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify creation
        cy.get(page.elements.salesChannelNameInput).should('have.value', '1st Epic Sales Channel');

        // Check if the sales channel can be used in other modules
        cy.clickMainMenuItem({
            targetPath: '#/sw/customer/index',
            mainMenuId: 'sw-customer'
        });
        cy.get('.smart-bar__actions a[href="#/sw/customer/create"]').click();
        cy.get('select[name=sw-field--customer-salesChannelId]').select('1st Epic Sales Channel');
    });

    it('@package @general: update and read sales channel', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/sales-channel/*',
            method: 'patch'
        }).as('saveData');

        // Edit and verify change in sales channel
        page.openSalesChannel('Storefront', 4);
        cy.get(page.elements.salesChannelNameInput).clear();
        cy.get(page.elements.salesChannelNameInput).type('Channel No 9');
        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.contains('Channel No 9');
    });

    it('@package @general: delete sales channel', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/sales-channel/*',
            method: 'delete'
        }).as('deleteData');

        // Delete sales channel
        page.openSalesChannel('Headless', 3);
        page.deleteSingleSalesChannel('Headless');
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-admin-menu__sales-channel-item--4').should('not.exist');
    });
});

