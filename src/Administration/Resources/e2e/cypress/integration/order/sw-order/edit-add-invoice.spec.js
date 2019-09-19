// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Test order state', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name'
                    }
                });
            })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
            });
    });

    it('@package @order: add document to order', () => {
        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/_action/order/**/document/invoice',
            method: 'post'
        }).as('createDocumentCall');
        cy.route({
            url: '/api/v1/search/document',
            method: 'post'
        }).as('findDocumentCall');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Max Mustermann');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        // Find documents
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-context-menu-item:nth-of-type(3)',
            '.sw-order-document-grid-button'
        );

        // Generate invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
            .click();

        // Verify invoice
        cy.wait('@createDocumentCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.wait('@findDocumentCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0')
            .contains('Invoice');
    });
});
