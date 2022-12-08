// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Create credit note', () => {
    beforeEach(() => {
        cy.loginViaApi()
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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    // NEXT-21363
    it('@base @order: create credit note', { tags: ['quarantined', 'pa-customers-orders'] }, () => {
        const page = new OrderPageObject();

        cy.featureIsActive('v6.5.0.0').then(isActive => {
            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/_action/order/**/recalculate`,
                method: 'POST'
            }).as('orderRecalculateCall');

            const createInvoiceUrl = isActive
                ? `**/${Cypress.env('apiPath')}/_action/order/document/invoice/create`
                : `**/${Cypress.env('apiPath')}/_action/order/**/document/invoice`;

            cy.intercept({
                url: createInvoiceUrl,
                method: 'POST'
            }).as('createInvoice');

            const createCreditNoteUrl = isActive
                ? `**/${Cypress.env('apiPath')}/_action/order/document/credit_note/create`
                : `**/${Cypress.env('apiPath')}/_action/order/**/document/credit_note`;

            cy.intercept({
                url: createCreditNoteUrl,
                method: 'POST'
            }).as('createCreditNote');

            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/_action/order/**/document/invoice/preview*`,
                method: 'GET'
            }).as('onPreview');

            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/search/document-type`,
                method: 'POST'
            }).as('getDocumentTypes');

            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/_action/version/merge/order/**`,
                method: 'POST'
            }).as('orderSaveCall');

            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/search/order`,
                method: 'POST'
            }).as('orderSearchCall');

            cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
            cy.clickContextMenuItem(
                '.sw-order-list__order-view-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );

            cy.get(page.elements.tabs.general.gridCard).scrollIntoView();

            cy.clickContextMenuItem(
                '.sw-context-menu-item',
                '.sw-order-line-items-grid__actions-container .sw-button-group .sw-context-button',
                null,
                'Add credit'
            );

            cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--label`).dblclick().click();
            cy.get('#sw-field--item-label').type('Discount 100 Euro');
            cy.get('#sw-field--item-priceDefinition-price').clear().type('-100');

            cy.get(page.elements.dataGridInlineEditSave).click();
            cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
            cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

            cy.get(page.elements.smartBarSave).click();
            cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);
            cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

            page.changeActiveTab('documents');

            // Open Invoice modal
            cy.get(page.elements.tabs.documents.documentGrid)
                .scrollIntoView()
                .should('be.visible');

            cy.get(page.elements.loader).should('not.exist');

            cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
            cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

            cy.contains(page.elements.tabs.documents.documentTypeModalRadios, 'Invoice').click();

            cy.get('.sw-modal__footer .sw-button--primary')
                .should('not.be.disabled')
                .click();

            // Generate preview
            cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
            cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
            cy.get('.sw-order-document-settings-modal__preview-button').click();

            cy.wait('@onPreview').its('response.statusCode').should('equal', 200);

            // Generate invoice
            cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
            cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
            cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-button--primary`)
                .should('not.be.disabled')
                .click();

            cy.wait('@createInvoice').its('response.statusCode').should('equal', 200);

            cy.get(page.elements.loader).should('not.exist');

            cy.reload();

            cy.get(page.elements.tabs.documents.documentGrid).scrollIntoView();

            cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
            cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

            cy.contains(page.elements.tabs.documents.documentTypeModalRadios, 'Credit note').click();

            cy.get('.sw-modal__footer .sw-button--primary')
                .should('not.be.disabled')
                .click();

            cy.get('#sw-field--documentConfig-custom-invoiceNumber').select('1000');
            cy.get('#sw-field--documentConfig-documentComment').type('Always get a credit note');

            cy.get('.sw-modal__footer .sw-button--primary')
                .should('be.visible')
                .should('not.be.disabled')
                .click();

            // Wait for the credit note to be created
            cy.wait('@createCreditNote').its('response.statusCode').should('equal', 200);

            // Reloading the page is necessary to get rid off the view reloading after several $nextTicks
            cy.reload();

            cy.wait('@getDocumentTypes').its('response.statusCode').should('equal', 200);

            // check exists credit note
            cy.get('.sw-simple-search-field--form input[placeholder="Search all documents..."]')
                .scrollIntoView()
                .type('Credit note');

            cy.contains(`${page.elements.dataGridRow}--0`, 'Credit note');
        });
    });
});
