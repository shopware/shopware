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

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${page.elements.userMetadata}-user-name`)
                .contains('Max Mustermann');
            cy.get('.sw-order-user-card__metadata-price').contains('64');
            cy.get('.sw-order-base__label-sales-channel').contains('Storefront');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(page.elements.tabs.general.generalInfoCard).should('exist');
            cy.get(page.elements.tabs.general.summaryMainHeader)
                .contains(' - Max Mustermann (max.mustermann@example.com)');
            cy.get(page.elements.tabs.general.summaryMainTotal)
                .contains('64');
            cy.get(page.elements.tabs.general.summarySubDescription)
                .contains(`with Cash on delivery and`);

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
        });

        cy.get('.sw-order-detail__summary').scrollIntoView();
        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');
        cy.get(`${page.elements.dataGridRow}--0`).contains('64');
        cy.get(`${page.elements.dataGridRow}--0`).contains('19 %');

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-detail__summary').scrollIntoView();
            cy.get('.sw-address__headline').contains('Shipping address');
            cy.get('.sw-order-delivery-metadata .sw-address__location').contains('Bielefeld');

            cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();

            cy.clickContextMenuItem(
                '.sw-context-menu__content',
                page.elements.contextMenuButton,
                '.sw-order-detail-base'
            );
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-detail-general__line-item-grid-card').scrollIntoView();

            cy.clickContextMenuItem(
                '.sw-context-menu__content',
                page.elements.contextMenuButton,
                '.sw-order-detail-general__line-item-grid-card',
                'Show product'
            );
        });
        cy.get(page.elements.smartBarHeader).contains('Product name');
    });
});
