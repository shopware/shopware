/// <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';
const uuid = require('uuid/v4');

const ids = {
    '1': uuid().replace(/-/g, ''),
    '1.1': uuid().replace(/-/g, ''),
    '1.1.1': uuid().replace(/-/g, ''),
    '1.1.1.1': uuid().replace(/-/g, ''),
    '1.1.1.1.1': uuid().replace(/-/g, ''),
    '1.1.2': uuid().replace(/-/g, ''),
    '1.1.3': uuid().replace(/-/g, ''),
    '2': uuid().replace(/-/g, ''),
    '2.1': uuid().replace(/-/g, ''),
    '2.2': uuid().replace(/-/g, ''),
    '2.2.1': uuid().replace(/-/g, ''),
    '3': uuid().replace(/-/g, ''),
};

function getLineItem(id, children = [], position = 0) {
    const mockNumber = parseInt(id.replace(/\./g, ''));
    return {
        id: ids[id],
        referencedId: ids['1'],
        identifier: ids[id],
        type: 'product',
        stackable: true,
        quantity: mockNumber * 10,
        label: `LineItem ${id}`,
        productId: ids['1'],
        payload: {
            productNumber: 'NEST-1',
        },
        position,
        children,
        price: {
            quantity: 2,
            taxRules: [{
                taxRate: 20.0,
                percentage: 100.0,
            }],
            listPrice: null,
            unitPrice: mockNumber * 0.008,
            totalPrice: mockNumber * 0.01,
            referencePrice: null,
            calculatedTaxes: [{
                tax: mockNumber * 0.002,
                price: mockNumber * 0.01,
                taxRate: 20.0,
            }],
        },
    };
}

describe('Order: Visual tests', () => {
    beforeEach(() => {
        cy.createProductFixture({
            id: ids['1'],
            name: 'Awesome product product',
            label: 'Awesome product product',
            productNumber: 'NEST-1',
            description: 'l33t',
        }).then(() => {
            return cy.createAdminOrder({
                itemRounding: {"decimals": 2, "interval": 0.01, "roundForNet": true},
                totalRounding: {"decimals": 2, "interval": 0.01, "roundForNet": true},
                lineItems: [
                    getLineItem('1', [
                        getLineItem('1.1', [
                            getLineItem('1.1.1', [
                                getLineItem('1.1.1.1', [
                                    getLineItem('1.1.1.1.1'),
                                ]),
                            ]),
                            getLineItem('1.1.2'),
                            getLineItem('1.1.3'),
                        ]),
                    ], 1),
                    getLineItem('2', [
                        getLineItem('2.1'),
                        getLineItem('2.2', [
                            getLineItem('2.2.1'),
                        ]),
                    ], 2),
                    getLineItem('3', [], 3),
                ],
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@visual: check appearance of basic order workflow', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(`${page.elements.dataGridRow}--0`).scrollIntoView();

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.takeSnapshot('Order listing with nested line items', '.sw-order-line-items-grid__data-grid');

        cy.get(`${page.elements.dataGridRow}--0 .sw-order-line-items-grid__item-nested-indicator`).click();
        cy.takeSnapshot('Nested line items modal', '.sw-order-line-items-grid__data-grid');
    });
});
