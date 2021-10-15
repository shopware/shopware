// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

describe('Order: Test ACL privileges', () => {
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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@acl: can read order', () => {
        const page = new OrderPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'order',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        });

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
            cy.get(page.elements.tabs.general.summaryMainHeader)
                .contains('- Max Mustermann (max.mustermann@example.com)');

            cy.get(page.elements.tabs.general.summaryMainTotal)
                .contains('64');

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
        cy.get(`${page.elements.dataGridRow}--0`).contains('Product name');
        cy.get(`${page.elements.dataGridRow}--0`).contains('64');
        cy.get(`${page.elements.dataGridRow}--0`).contains('19 %');

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-detail__summary').scrollIntoView();
            cy.get('.sw-address__headline').contains('Shipping address');
            cy.get('.sw-order-delivery-metadata .sw-address__location').contains('Bielefeld');
            cy.get('.sw-order-state-card__history-entry .sw-order-state-card__text').contains('Open');
        });
    });

    it('@acl: can edit order', () => {
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
        });

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(`${page.elements.userMetadata}-user-name`)
                .contains('Max Mustermann');

            // click edit button
            cy.get('.sw-order-detail__smart-bar-edit-button').click();
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get(page.elements.tabs.general.summaryMainHeader)
                .contains('- Max Mustermann (max.mustermann@example.com)');

            cy.get(page.elements.tabs.general.summaryMainTotal)
                .contains('64');
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

    it('@acl: can delete order', () => {
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
        });

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-order-list__confirm-delete-text`).contains(
            'Do you really want to delete this order (10000)?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        cy.wait('@orderDeleteCall').its('response.statusCode').should('equal', 204);
    });
});
