// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Create credit note', () => {
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

    it('@base @order: create credit note', () => {
        const page = new OrderPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/**/recalculate`,
            method: 'POST'
        }).as('orderRecalculateCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/*/document/invoice`,
            method: 'POST'
        }).as('createInvoice');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/*/document/credit_note`,
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

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${page.elements.userMetadata}-user-name`).contains('Max Mustermann');
            cy.get('.sw-order-detail__smart-bar-edit-button').click();

            cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        });

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

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            page.changeActiveTab('documents');
        });

        // Open Invoice modal
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

        // Generate preview
        cy.get(page.elements.tabs.documents.documentSettingsModal).should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
        cy.get('#sw-order-document-settings-modal__preview-button').click();

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

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.clickContextMenuItem(
                '.sw-context-menu-item',
                '.sw-order-document-grid-button',
                null,
                'Credit note'
            );
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(page.elements.tabs.documents.addDocumentButton).should('be.visible').click();
            cy.get(page.elements.tabs.documents.documentTypeModal).should('be.visible');

            cy.get(page.elements.tabs.documents.documentTypeModalRadios).contains('Credit note').click();

            cy.get('.sw-modal__footer .sw-button--primary')
                .should('not.be.disabled')
                .click();
        });

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

        cy.get(`${page.elements.dataGridRow}--0`).contains('Credit note');
    });
});
