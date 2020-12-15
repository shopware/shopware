/// <reference types="Cypress" />

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

    it('@base @order: add document to order', () => {
        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/**/document/invoice`,
            method: 'post'
        }).as('createDocumentCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/document`,
            method: 'post'
        }).as('findDocumentCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'post'
        }).as('findOrder');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        // Find documents
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid').should('be.visible');
        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-document-grid-button',
            null,
            'Invoice'
        );

        // Generate invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
            .click();

        // Verify invoice
        cy.wait('@createDocumentCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.responseBody).to.have.property('documentId');
            expect(xhr.responseBody).to.have.property('documentDeepLink');

            const documentId = xhr.response.body.documentId;
            const documentDeepLink = xhr.response.body.documentDeepLink;

            return cy.request(`/api/v*/_action/document/${documentId}/${documentDeepLink}`);
        }).then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            expect(xhr.headers).to.have.property('content-type', 'application/pdf');
        });

        cy.wait('@findDocumentCall').then((xhr) => {
            cy.log(`metal.total${xhr.responseBody.meta.total}`);
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@findOrder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@findDocumentCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0')
            .contains('Invoice');

        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0 .sw-data-grid__cell--actions .sw-context-button').click();
        cy.get('.sw-context-menu-item').contains('Download');
    });
});
