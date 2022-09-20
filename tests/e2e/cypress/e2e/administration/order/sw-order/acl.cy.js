// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Test ACL privileges', () => {
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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@acl: can read order', { tags: ['pa-customers-orders'] }, () => {
        const page = new OrderPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'order',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.contains(`${page.elements.userMetadata}-user-name`, 'Max Mustermann');
            cy.contains('.sw-order-user-card__metadata-price', '49.98');
            cy.contains('.sw-order-base__label-sales-channel', 'Storefront');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.contains(page.elements.tabs.general.summaryMainHeader, '- Max Mustermann (max.mustermann@example.com)');

            cy.contains(page.elements.tabs.general.summaryMainTotal, '49.98');

            cy.get(page.elements.stateSelects.orderStateSelect)
                .find('input')
                .should('have.attr', 'placeholder', 'Open');

            cy.get(page.elements.stateSelects.orderDeliveryStateSelect)
                .find('input')
                .should('have.attr', 'placeholder', 'Open');

            cy.get(page.elements.stateSelects.orderTransactionStateSelect)
                .find('input')
                .should('have.attr', 'placeholder', 'Open');
        });

        cy.get('.sw-order-detail__summary').scrollIntoView();
        cy.contains(`${page.elements.dataGridRow}--0`, 'Product name');
        cy.contains(`${page.elements.dataGridRow}--0`, '49.98');
        cy.contains(`${page.elements.dataGridRow}--0`, '19 %');

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-detail__summary').scrollIntoView();
            cy.contains('.sw-address__headline', 'Shipping address');
            cy.contains('.sw-order-delivery-metadata .sw-address__location', 'Bielefeld');
            cy.contains('.sw-order-state-card__history-entry .sw-order-state-card__text', 'Open');
        });
    });

    it('@acl: can edit order', { tags: ['pa-customers-orders'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/order/**/product/**`,
            method: 'POST'
        }).as('orderAddProductCall');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/version/merge/order/**`,
            method: 'POST'
        }).as('orderSaveCall');

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.intercept({
                url: `**/${Cypress.env('apiPath')}/_action/order/**/recalculate`,
                method: 'POST'
            }).as('recalculateCall');
        });

        const page = new OrderPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'order',
                role: 'viewer'
            },
            {
                key: 'order',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.contains(`${page.elements.userMetadata}-user-name`, 'Max Mustermann');

            // click edit button
            cy.get('.sw-order-detail__smart-bar-edit-button').click();
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.contains(page.elements.tabs.general.summaryMainHeader,
                '- Max Mustermann (max.mustermann@example.com)');

            cy.contains(page.elements.tabs.general.summaryMainTotal, '49.98');
        });

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        });

        // click "add product"
        cy.get(page.elements.tabs.general.addProductButton).click();

        // select product
        cy.get(`${page.elements.dataGridRow}--0 > ${page.elements.dataGridColumn}--label`)
            .dblclick();

        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        cy.get(page.elements.dataGridInlineEditSave).click();
        cy.wait('@orderAddProductCall').its('response.statusCode').should('equal', 204);

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.wait('@recalculateCall').its('response.statusCode').should('equal', 204);
        });

        // click save
        cy.get(page.elements.smartBarSave).click();

        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);
    });

    it('@acl: can delete order', { tags: ['pa-customers-orders'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/order/**`,
            method: 'delete'
        }).as('orderDeleteCall');

        const page = new OrderPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'order',
                role: 'viewer'
            },
            {
                key: 'order',
                role: 'editor'
            },
            {
                key: 'order',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains(`${page.elements.modal} .sw-order-list__confirm-delete-text`,
            'Do you really want to delete this order (10000)?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        cy.wait('@orderDeleteCall').its('response.statusCode').should('equal', 204);
    });
});
