// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Create credit note', () => {
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

    it('@base @order: create credit note', {tags: ['pa-customers-orders', 'quarantined'/*, 'VUE3_SKIP'*/]}, () => {
        const page = new OrderPageObject();

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-line-items-grid__actions-container .sw-button-group .sw-context-button',
            null,
            'Add credit',
        );

        cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--label`).dblclick();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/**/recalculate`,
            method: 'POST',
        }).as('orderRecalculateCall');

        cy.get('#sw-field--item-label').type('Discount 100 Euro');
        cy.get('#sw-field--item-priceDefinition-price').clearTypeAndCheck('-100');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('orderSearchCall');

        cy.get(page.elements.dataGridInlineEditSave).click();
        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/version/merge/order/**`,
            method: 'POST',
        }).as('orderSaveCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/order`,
            method: 'POST',
        }).as('orderSearchCall');

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

        cy.contains('.sw-field__radio-option-label', 'Invoice').click();

        cy.get('.sw-modal__footer .sw-button--primary')
            .should('not.be.disabled')
            .click();

        // Generate preview
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/**/document/invoice/preview*`,
            method: 'GET',
        }).as('onPreview');

        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
        cy.get('.sw-order-document-settings-modal__preview-button').click();

        cy.wait('@onPreview').its('response.statusCode').should('equal', 200);

        // Generate invoice
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/document/invoice/create`,
            method: 'POST',
        }).as('createInvoice');

        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
        cy.get(`${page.elements.tabs.documents.documentSettingsModal} .sw-order-document-settings-modal__create`).should('not.be.disabled').click();

        cy.wait('@createInvoice').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.loader).should('not.exist');

        cy.reload();

        cy.get(page.elements.tabs.documents.documentGrid).scrollIntoView();

        cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
        cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

        cy.contains('.sw-field__radio-option-label', 'Credit note').click();

        cy.get('.sw-modal__footer .sw-button--primary')
            .should('not.be.disabled')
            .click();

        cy.get('.sw-order-document-settings-credit-note-modal__invoice-select .sw-block-field__block select').select(1);
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a credit note');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/document/credit_note/create`,
            method: 'POST',
        }).as('createCreditNote');

        cy.get('.sw-modal__footer .sw-order-document-settings-modal__create')
            .should('be.visible')
            .should('not.be.disabled')
            .click();

        // Wait for the credit note to be created
        cy.wait('@createCreditNote').its('response.statusCode').should('equal', 200);

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/document-type`,
            method: 'POST',
        }).as('getDocumentTypes');

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
