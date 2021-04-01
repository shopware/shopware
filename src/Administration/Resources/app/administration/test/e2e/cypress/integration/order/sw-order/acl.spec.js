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
        cy.get('.sw-order-state-card__history-entry .sw-order-state-card__text').contains('Open');
    });

    it('@acl: can edit order', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/order/**/product/**`,
            method: 'post'
        }).as('orderAddProductCall');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/version/merge/order/**`,
            method: 'post'
        }).as('orderSaveCall');

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

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        // click edit button
        cy.get('.sw-order-detail__smart-bar-edit-button').click();

        // click "add product"
        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        cy.get('.sw-order-line-items-grid__actions-container-add-product-btn').click();

        // select product
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label').dblclick();

        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        cy.get('.sw-data-grid__inline-edit-save').click();
        cy.wait('@orderAddProductCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // click save
        cy.get('.sw-order-detail__smart-bar-save-button').click();

        cy.wait('@orderSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@acl: can delete order', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/order/**`,
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

        cy.wait('@orderDeleteCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
