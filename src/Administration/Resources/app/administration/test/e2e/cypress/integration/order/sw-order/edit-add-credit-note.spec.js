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
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');

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

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${page.elements.userMetadata}-user-name`)
                .contains('Max Mustermann');
            cy.get('.sw-order-detail__smart-bar-edit-button').click();
        });

        cy.get('.sw-context-button .sw-button--ghost').click();
        cy.get('.sw-order-line-items-grid__can-create-discounts-button').click();

        cy.get('.sw-data-grid__row--0').dblclick();
        cy.get('#sw-field--item-label').type('Discount 100 Euro');
        cy.get('#sw-field--item-priceDefinition-price').clear().type('-100');

        cy.get('.sw-data-grid__inline-edit-save').click();

        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);

        cy.get('.sw-order-detail__smart-bar-save-button').click();


        // Open Invoice modal
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

        // Generate preview
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
        cy.get('#sw-order-document-settings-modal__preview-button').click();

        cy.wait('@onPreview')
            .its('response.statusCode').should('equal', 200);

        // Generate invoice
        cy.get('.sw-order-document-settings-modal__settings-modal').should('be.visible');
        cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
        cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary').click();

        cy.wait('@createInvoice').its('response.statusCode').should('equal', 200);

        // Open create edit note modal
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid').should('be.visible');
        cy.get(page.elements.loader).should('not.exist');

        cy.reload();
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.clickContextMenuItem(
                '.sw-context-menu-item',
                '.sw-order-document-grid-button',
                null,
                'Credit note'
            );
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-document-grid-button').should('be.visible').click();
            cy.get('.sw-order-select-document-type-modal').should('be.visible');

            cy.get('.sw-field__radio-group').contains('Credit note').click();

            cy.get('.sw-modal__footer .sw-button--primary').click();
        });

        cy.get('#sw-field--documentConfig-custom-invoiceNumber').select('1000');
        cy.get('#sw-field--documentConfig-documentComment').type('Always get a credit note');

        cy.get('.sw-modal__footer .sw-button--primary').should('be.visible');
        cy.get('.sw-modal__footer .sw-button--primary').click();

        // Wait for the credit note to be created
        cy.wait('@createCreditNote').its('response.statusCode').should('equal', 200);

        // Reloading the page is necessary to get rid off the view reloading after several $nextTicks
        cy.reload();
        cy.wait('@getDocumentTypes').its('response.statusCode').should('equal', 200);

        // check exists credit note
        cy.get('.sw-simple-search-field--form input[placeholder="Search all documents..."]').scrollIntoView();
        cy.get('.sw-simple-search-field--form input[placeholder="Search all documents..."]').type('Credit note');
        cy.get('.sw-data-grid__row.sw-data-grid__row--0').contains('Credit note');
    });
});
