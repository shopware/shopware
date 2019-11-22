// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Read order', () => {
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

    it('@package @order: read order', () => {
        const page = new OrderPageObject();

        cy.get(`${page.elements.dataGridRow}--0`).contains('Max Mustermann');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');
        cy.get('.sw-order-user-card__metadata-price').contains('64');
        cy.get('.sw-order-base__label-sales-channel').contains('Storefront');
        cy.get('.sw-order-detail__summary').scrollIntoView();
        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');
        cy.get(`${page.elements.dataGridRow}--0`).contains('64');
        cy.get(`${page.elements.dataGridRow}--0`).contains('19 %');
        cy.get('.sw-order-detail__summary').scrollIntoView();
        cy.get('.sw-address__headline').contains('Shipping address');
        cy.get('.sw-order-delivery-metadata .sw-address__location').contains('Bielefeld');
        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-context-menu__content',
            page.elements.contextMenuButton
        );
        cy.get(page.elements.smartBarHeader).contains('Product name');
    });
});
