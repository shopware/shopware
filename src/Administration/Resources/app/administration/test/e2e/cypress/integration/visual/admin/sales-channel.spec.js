/// <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic sales channel workflow', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel`,
            method: 'post'
        }).as('saveData');

        // Open sales channel creation
        cy.get('.sw-admin-menu__headline').contains('Sales Channel');

        cy.get('.sw-admin-menu__headline-action').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Sales channel create - modal', '.sw-sales-channel-modal');

        cy.get('.sw-sales-channel-modal__title').contains('Add Sales Channel');
        cy.get(`${page.elements.gridRow}--0 .sw-sales-channel-modal-grid__item-name`).click();
        cy.get('.sw-sales-channel-modal__title').contains('Storefront - details');
        cy.get('.sw-sales-channel-modal__add-sales-channel-action').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Sales channel create', '.sw-sales-channel-detail-base');

        // Fill in form and save new sales channel
        page.fillInBasicSalesChannelData('1st Epic Sales Channel');

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            // Take snapshot for visual testing
            cy.takeSnapshot('Sales channel detail', '.sw-sales-channel-detail-base');
        });
    });
});

