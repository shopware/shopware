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

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${page.elements.userMetadata}-user-name`).contains('Max Mustermann');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            page.changeActiveTab('documents');
        });

        // Find documents
        cy.get(page.elements.tabs.documents.documentGrid)
            .scrollIntoView()
            .should('be.visible');

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
            cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
            cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

            cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

            cy.get('.sw-modal__footer .sw-button--primary')
                .should('not.be.disabled')
                .click();
        });

        // Generate invoice
        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-button--primary`).click();

        // Verify invoice
        cy.wait('@createDocumentCall').its('response.statusCode').should('equal', 200);
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

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            page.changeActiveTab('documents');
        });

        cy.get(page.elements.tabs.documents.documentGrid).scrollIntoView();
        cy.get(`${page.elements.tabs.documents.documentGrid} ${page.elements.dataGridRow}--0`)
            .should('be.visible')
            .contains('Invoice');

        cy.get(`${page.elements.tabs.documents.documentGrid} ${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions .sw-context-button`)
            .click();

        cy.get('.sw-context-menu-item').contains('Download');
    });

    it('@base @order: add document to order with existing invoice number', () => {
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

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${page.elements.userMetadata}-user-name`).contains('Max Mustermann');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            page.changeActiveTab('documents');
        });

        // Find documents
        cy.get(page.elements.tabs.documents.documentGrid)
            .scrollIntoView()
            .should('be.visible');

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
            cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
            cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

            cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

            cy.get('.sw-modal__footer .sw-button--primary')
                .should('not.be.disabled')
                .click();
        });

        cy.wait('@reserveDocumentNumberRange').its('response.statusCode').should('equal', 200);

        // Generate first invoice
        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');

        cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
            .then((invoiceNumber) => {
                cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-button--primary`)
                    .should('not.be.disabled')
                    .click();

                // Verify first invoice
                cy.wait('@createDocumentCall').its('response.statusCode').should('equal', 200);
                cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);
                cy.wait('@findOrder').its('response.statusCode').should('equal', 200);
                cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);

                // Start to create a second invoice
                cy.get(page.elements.tabs.documents.documentGrid)
                    .scrollIntoView()
                    .should('be.visible');

                cy.skipOnFeature('FEATURE_NEXT_7530', () => {
                    cy.clickContextMenuItem(
                        '.sw-context-menu-item',
                        '.sw-order-document-grid-button',
                        null,
                        'Invoice'
                    );
                });

                cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
                    cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
                    cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

                    cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

                    cy.get('.sw-modal__footer .sw-button--primary')
                        .should('not.be.disabled')
                        .click();
                });

                cy.wait('@reserveDocumentNumberRange').its('response.statusCode').should('equal', 200);

                // Generate second invoice with same invoice number with first invoice
                cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
                cy.get('#sw-field--documentConfig-documentNumber').clear().type(invoiceNumber);
                cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
                    .then((invoiceNumberCheck) => {
                        if (invoiceNumberCheck !== invoiceNumber) {
                            cy.get('#sw-field--documentConfig-documentNumber').clear().type(invoiceNumber);
                        }
                    });

                cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-button--primary`)
                    .should('not.be.disabled')
                    .click();

                // Verify second invoice and check error notification
                cy.wait('@createDocumentCall').its('response.statusCode').should('equal', 400);

                cy.awaitAndCheckNotification(`Document number ${invoiceNumber} has already been allocated.`);
            });
    });
});
