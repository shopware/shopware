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

    it('@base @order: add document to order', () => {
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/**/document/invoice`,
            method: 'POST'
        }).as('createDocumentCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/document`,
            method: 'POST'
        }).as('findDocumentCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
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

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.clickContextMenuItem(
                '.sw-context-menu-item',
                '.sw-order-document-grid-button',
                null,
                'Invoice'
            );
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-document-grid-button').should('be.visible').click();
            cy.get('.sw-order-select-document-type-modal').should('be.visible');

            cy.get('.sw-field__radio-group').contains('Invoice').click();

            cy.get('.sw-modal__footer .sw-button--primary').click();
        });

        // Generate invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
            .click();

        // Verify invoice
        cy.wait('@createDocumentCall')
            .its('response.statusCode').should('equal', 200);
        cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);

        cy.wait('@findOrder').its('response.statusCode').should('equal', 200);

        cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);

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

        cy.get('.sw-order-detail-base__document-grid .sw-data-grid__row--0 .sw-data-grid__cell--actions .sw-context-button')
            .click();
        cy.get('.sw-context-menu-item').contains('Download');
    });

    it('@base @order: add document to order with existing invoice number', () => {
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/order/**/document/invoice`,
            method: 'POST'
        }).as('createDocumentCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/document`,
            method: 'POST'
        }).as('findDocumentCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('findOrder');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/number-range/reserve/document_invoice/*`,
            method: 'GET'
        }).as('reserveDocumentNumberRange');

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

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.clickContextMenuItem(
                '.sw-context-menu-item',
                '.sw-order-document-grid-button',
                null,
                'Invoice'
            );
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-document-grid-button').should('be.visible').click();
            cy.get('.sw-order-select-document-type-modal').should('be.visible');

            cy.get('.sw-field__radio-group').contains('Invoice').click();

            cy.get('.sw-modal__footer .sw-button--primary').click();
        });

        cy.wait('@reserveDocumentNumberRange')
            .its('response.statusCode').should('equal', 200);

        // Generate first invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');

        cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
            .then((invoiceNumber) => {
                cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
                    .click();

                // Verify first invoice
                cy.wait('@createDocumentCall')
                    .its('response.statusCode').should('equal', 200);

                cy.wait('@findDocumentCall')
                    .its('response.statusCode').should('equal', 200);

                cy.wait('@findOrder')
                    .its('response.statusCode').should('equal', 200);

                cy.wait('@findDocumentCall')
                    .its('response.statusCode').should('equal', 200);

                // Start to create a second invoice
                cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
                cy.get('.sw-order-detail-base__document-grid').should('be.visible');


                cy.skipOnFeature('FEATURE_NEXT_7530', () => {
                    cy.clickContextMenuItem(
                        '.sw-context-menu-item',
                        '.sw-order-document-grid-button',
                        null,
                        'Invoice'
                    );
                });

                cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
                    cy.get('.sw-order-document-grid-button').should('be.visible').click();
                    cy.get('.sw-order-select-document-type-modal').should('be.visible');

                    cy.get('.sw-field__radio-group').contains('Invoice').click();

                    cy.get('.sw-modal__footer .sw-button--primary').click();
                });

                cy.wait('@reserveDocumentNumberRange')
                    .its('response.statusCode').should('equal', 200);

                // Generate second invoice with same invoice number with first invoice
                cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
                cy.get('#sw-field--documentConfig-documentNumber').clear().type(invoiceNumber);
                cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
                    .then((invoiceNumberCheck) => {
                        if (invoiceNumberCheck !== invoiceNumber) {
                            cy.get('#sw-field--documentConfig-documentNumber').clear().type(invoiceNumber);
                        }
                    });
                cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary')
                    .click();

                // Verify second invoice and check error notification
                cy.wait('@createDocumentCall')
                    .its('response.statusCode').should('equal', 400);
                cy.awaitAndCheckNotification(`Document number ${invoiceNumber} has already been allocated.`);
            });
    });
});
