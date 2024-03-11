// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Test order state', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.searchViaAdminApi({
                endpoint: 'product',
                data: {
                    field: 'name',
                    value: 'Product name',
                },
            });
        })
            .then((result) => {
                return cy.createGuestOrder(result.id);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @order: add document to order', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();
        const createInvoiceUrl = `**/${Cypress.env('apiPath')}/_action/order/document/invoice/create`;

        // Request we want to wait for later
        cy.intercept({
            url: createInvoiceUrl,
            method: 'POST',
        }).as('createDocumentCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/document`,
            method: 'POST',
        }).as('findDocumentCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('findOrder');

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        page.changeActiveTab('documents');

        // Find documents
        cy.get(page.elements.tabs.documents.documentGrid)
            .scrollIntoView()
            .should('be.visible');

        cy.get('.sw-loader').should('not.exist');

        cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
        cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

        cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

        cy.get('.sw-modal__footer .sw-button--primary')
            .should('not.be.disabled')
            .click();

        // Generate invoice
        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-order-document-settings-modal__create`).click();

        // Verify invoice
        cy.wait('@createDocumentCall').its('response.statusCode').should('equal', 200);
        cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);
        cy.wait('@findOrder').its('response.statusCode').should('equal', 200);
        cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        page.changeActiveTab('documents');

        cy.get(page.elements.tabs.documents.documentGrid).scrollIntoView();
        cy.contains(`${page.elements.tabs.documents.documentGrid} ${page.elements.dataGridRow}--0`, 'Invoice')
            .should('be.visible');

        cy.get(`${page.elements.tabs.documents.documentGrid} ${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions .sw-context-button`)
            .click();

        cy.contains('.sw-context-menu-item', 'Download');
    });

    it('@base @order: add document to order with existing invoice number', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new OrderPageObject();
        const createInvoiceUrl = `**/${Cypress.env('apiPath')}/_action/order/document/invoice/create`;

        cy.intercept({
            url: createInvoiceUrl,
            method: 'POST',
        }).as('createDocumentCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/document`,
            method: 'POST',
        }).as('findDocumentCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('findOrder');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/number-range/reserve/document_invoice/*`,
            method: 'GET',
        }).as('reserveDocumentNumberRange');

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        page.changeActiveTab('documents');

        // Find documents
        cy.get(page.elements.tabs.documents.documentGrid)
            .scrollIntoView()
            .should('be.visible');

        cy.get('.sw-loader').should('not.exist');

        cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
        cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

        cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

        cy.get('.sw-modal__footer .sw-button--primary')
            .should('not.be.disabled')
            .click();

        cy.wait('@reserveDocumentNumberRange').its('response.statusCode').should('equal', 200);

        // Generate first invoice
        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');

        cy.get('#sw-field--documentConfig-documentNumber').invoke('val')
            .then((invoiceNumber) => {
                cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-order-document-settings-modal__create`)
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

                cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
                cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

                cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

                cy.get('.sw-modal__footer .sw-button--primary')
                    .should('not.be.disabled')
                    .click();

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

                cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-order-document-settings-modal__create`)
                    .should('not.be.disabled')
                    .click();

                // Verify second invoice and check error notification
                cy.wait('@createDocumentCall').its('response').then(response => {
                    expect(response.statusCode).to.equal(200);
                    expect(Object.values(response.body.errors)[0][0].detail).to.equal(`Document number ${invoiceNumber} has already been allocated.`);
                });

                cy.awaitAndCheckNotification(`Document number ${invoiceNumber} has already been allocated.`);
            });
    });

    it('@order: upload customer document file to document order', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();
        const createInvoiceUrl = `**/${Cypress.env('apiPath')}/_action/order/document/invoice/create`;

        // Request we want to wait for later
        cy.intercept({
            url: createInvoiceUrl,
            method: 'POST',
        }).as('createDocumentCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/document`,
            method: 'POST',
        }).as('findDocumentCall');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('findOrder');

        cy.contains(`${page.elements.dataGridRow}--0`,'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        page.changeActiveTab('documents');

        // Find documents
        cy.get(page.elements.tabs.documents.documentGrid)
            .scrollIntoView()
            .should('be.visible');

        cy.get('.sw-loader').should('not.exist');

        cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
        cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

        cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Invoice').click();

        cy.get('.sw-modal__footer .sw-button--primary')
            .should('not.be.disabled')
            .click();

        // Generate invoice
        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a bill');
        cy.get('.sw-order-document-settings-modal__file-toggle input[type="checkbox"]').check().should('be.checked');

        cy.get('.sw-media-upload-v2').should('be.visible');

        cy.get('.sw-media-upload-v2__file-input').attachFile('img/sw-test-image.png');
        cy.awaitAndCheckNotification('The selected file "sw-test-image.png" has an unsupported format. Please use one of the following types: application/pdf.');
        cy.get('.sw-media-upload-v2__file-headline').should('not.exist');

        cy.get('.sw-media-upload-v2__file-input').attachFile('pdf/sample.pdf');
        cy.get('.sw-media-upload-v2__file-headline')
            .should('be.visible')
            .contains('sample.pdf');

        cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-order-document-settings-modal__create`).click();

        // Verify invoice
        cy.wait('@createDocumentCall').its('response.statusCode').should('equal', 200);
        cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);
        cy.wait('@findOrder').its('response.statusCode').should('equal', 200);
        cy.wait('@findDocumentCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0`,'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        page.changeActiveTab('documents');

        cy.get(page.elements.tabs.documents.documentGrid).scrollIntoView();
        cy.get(`${page.elements.tabs.documents.documentGrid} ${page.elements.dataGridRow}--0`)
            .should('be.visible')
            .contains('Invoice');

        cy.get(`${page.elements.tabs.documents.documentGrid} ${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions .sw-context-button`)
            .click();

        cy.contains('.sw-context-menu-item','Download');
    });
});
