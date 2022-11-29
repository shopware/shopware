// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Read order', () => {
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

    it('@package @order: read order', { tags: ['pa-customers-orders'] }, () => {
        const page = new OrderPageObject();

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(page.elements.tabs.general.generalInfoCard).should('exist');
        cy.contains(page.elements.tabs.general.summaryMainHeader, ' - Max Mustermann (max.mustermann@example.com)');
        cy.contains(page.elements.tabs.general.summaryMainTotal, '49.98');
        cy.contains(page.elements.tabs.general.summarySubDescription, `with Cash on delivery and`);

        cy.get(page.elements.tabs.general.summaryStateSelects)
            .should('exist')
            .should('have.length', 3);

        cy.get(page.elements.stateSelects.orderTransactionStateSelect)
            .find('.sw-single-select__selection-input')
            .should('have.attr', 'placeholder', 'Open');

        cy.get(page.elements.stateSelects.orderDeliveryStateSelect)
            .find('.sw-single-select__selection-input')
            .should('have.attr', 'placeholder', 'Open');

        cy.get(page.elements.stateSelects.orderStateSelect)
            .find('.sw-single-select__selection-input')
            .should('have.attr', 'placeholder', 'Open');

        cy.get('.sw-order-detail__summary').scrollIntoView();
        cy.contains(`${page.elements.dataGridRow}--0`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0`, '49.98');
        cy.contains(`${page.elements.dataGridRow}--0`, '19 %');

        cy.get('.sw-order-detail-general__line-item-grid-card').scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-context-menu__content',
            page.elements.contextMenuButton,
            '.sw-order-detail-general__line-item-grid-card',
            'Show product'
        );

        cy.contains(page.elements.smartBarHeader, 'Product name');
    });
});
