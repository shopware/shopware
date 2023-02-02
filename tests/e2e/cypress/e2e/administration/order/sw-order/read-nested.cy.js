// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

const uuid = require('uuid/v4');

const ids = {
    1: uuid().replace(/-/g, ''),
    1.1: uuid().replace(/-/g, ''),
    '1.1.1': uuid().replace(/-/g, ''),
    '1.1.1.1': uuid().replace(/-/g, ''),
    '1.1.1.1.1': uuid().replace(/-/g, ''),
    '1.1.2': uuid().replace(/-/g, ''),
    '1.1.3': uuid().replace(/-/g, ''),
    2: uuid().replace(/-/g, ''),
    2.1: uuid().replace(/-/g, ''),
    2.2: uuid().replace(/-/g, ''),
    '2.2.1': uuid().replace(/-/g, ''),
    3: uuid().replace(/-/g, '')
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
        children,
        price: {
            quantity: 2,
            taxRules: [{
                taxRate: 20.0,
                percentage: 100.0
            }],
            listPrice: null,
            unitPrice: mockNumber * 0.008,
            totalPrice: mockNumber * 0.01,
            referencePrice: null,
            calculatedTaxes: [{
                tax: mockNumber * 0.002,
                price: mockNumber * 0.01,
                taxRate: 20.0
            }]
        },
        position: position
    };
}

describe('Order: Read order with nested line items', () => {
    beforeEach(() => {
        cy.loginViaApi().then(() => {
            return cy.createProductFixture({
                id: ids['1'],
                name: 'Awesome product product',
                label: 'Awesome product product',
                productNumber: 'NEST-1',
                description: 'l33t'
            });
        }).then(() => {
            return cy.createAdminOrder({
                lineItems: [
                    getLineItem('1', [
                        getLineItem('1.1', [
                            getLineItem('1.1.1', [
                                getLineItem('1.1.1.1', [
                                    getLineItem('1.1.1.1.1')
                                ])
                            ]),
                            getLineItem('1.1.2'),
                            getLineItem('1.1.3')
                        ])
                    ], 0),
                    getLineItem('2', [
                        getLineItem('2.1'),
                        getLineItem('2.2', [
                            getLineItem('2.2.1')
                        ])
                    ], 1),
                    getLineItem('3', [], 3)
                ]
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @order: can open and view nested line items in its modal', { tags: ['pa-customers-orders'] }, () => {
        const page = new OrderPageObject();

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Check if the labels are rendered correctly
        cy.contains(`${page.elements.dataGridRow}--0 .sw-order-line-items-grid__item-label`, 'LineItem 1');
        cy.contains(`${page.elements.dataGridRow}--1 .sw-order-line-items-grid__item-label`, 'LineItem 2');
        cy.contains(`${page.elements.dataGridRow}--2 .sw-order-line-items-grid__item-label`, 'LineItem 3');

        // Check if items with children have a button to toggle the nested line items modal
        cy.get(`${page.elements.dataGridRow}--0 .sw-order-line-items-grid__item-product`)
            .children()
            .should('have.length', '2');
        cy.get(`${page.elements.dataGridRow}--1 .sw-order-line-items-grid__item-product`)
            .children()
            .should('have.length', '2');
        cy.get(`${page.elements.dataGridRow}--2 .sw-order-line-items-grid__item-product`)
            .children()
            .should('have.length', '1');

        // Check first nested line item modal
        cy.get(`${page.elements.dataGridRow}--0 .sw-order-line-items-grid__item-nested-indicator`).click();

        // Check correct amount in each nesting level
        cy.contains(page.elements.modalTitle, 'Item: LineItem 1 - €0.01');
        cy.get('.sw-order-nested-line-items-row__nesting-level').should('have.length', '8');

        // Check the contents of the first row completely
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .contains('.sw-order-nested-line-items-row__label-content', 'LineItem 1.1');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .contains('.sw-order-nested-line-items-row__unit-price', '€0.088');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .contains('.sw-order-nested-line-items-row__quantity', '110');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .contains('.sw-order-nested-line-items-row__total-price', '€0.11');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .contains('.sw-order-nested-line-items-row__tax', '20 %');

        // Check the contents of the third row with some values
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .contains('.sw-order-nested-line-items-row__label-content', 'LineItem 1.1.1.1');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .contains('.sw-order-nested-line-items-row__quantity', '11110');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .contains('.sw-order-nested-line-items-row__total-price', '€11.11');

        // Close modal and check the second
        cy.get(page.elements.modalClose).click();
        cy.get(`${page.elements.dataGridRow}--1 .sw-order-line-items-grid__item-nested-indicator`).click();

        // Check correct amount in each nesting level
        cy.contains(page.elements.modalTitle, 'Item: LineItem 2 - €0.02');
        cy.get('.sw-order-nested-line-items-row__nesting-level').should('have.length', '1');

        // Check the contents of the third row with some values
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .contains('.sw-order-nested-line-items-row__label-content', 'LineItem 2.2.1');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .contains('.sw-order-nested-line-items-row__quantity', '2210');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .contains('.sw-order-nested-line-items-row__total-price', '€2.21');
    });
});
