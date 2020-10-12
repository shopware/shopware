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

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/**/recalculate`,
            method: 'post'
        }).as('orderRecalculateCall');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/*/document/invoice`,
            method: 'post'
        }).as('createInvoice');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/*/document/credit_note`,
            method: 'post'
        }).as('createCreditNote');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Max Mustermann');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');
        cy.get('.sw-order-detail__smart-bar-edit-button').click();
        cy.get('.sw-context-button .sw-button--ghost').click();
        cy.get('.sw-order-line-items-grid__can-create-discounts-button').click();

        cy.get('.sw-data-grid__row--0').dblclick();
        cy.get('#sw-field--item-label').type('Discount 100 Euro');
        cy.get('#sw-field--item-priceDefinition-price').clear().type('-100');

        cy.get('.sw-data-grid__inline-edit-save').click();

        cy.wait('@orderRecalculateCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-order-detail__smart-bar-save-button').click();


        // Open Invoice modal
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
        cy.get('#sw-field--documentConfig-documentComment').type('New invoice');
        cy.get('.sw-order-document-settings-modal__settings-modal .sw-button--primary').click();

        cy.wait('@createInvoice').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Open create edit note modal
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();
        cy.get('.sw-order-detail-base__document-grid').should('be.visible');
        cy.get(page.elements.loader).should('not.exist');

        cy.reload();
        cy.get('.sw-order-detail-base__document-grid').scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-document-grid-button',
            null,
            'Credit note'
        );

        cy.get('#sw-field--documentConfig-custom-invoiceNumber').select('1000');

        cy.get('.sw-modal__footer button.sw-button--primary').should('be.visible');
        cy.get('.sw-modal__footer button.sw-button--primary').click();

        cy.wait('@createCreditNote').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // check exsits credit note
        cy.get('.sw-simple-search-field--form input[placeholder="Search all documents..."]').type('Credit note');
        cy.get('.sw-data-grid__row.sw-data-grid__row--0').contains('Credit note');
    });
});
