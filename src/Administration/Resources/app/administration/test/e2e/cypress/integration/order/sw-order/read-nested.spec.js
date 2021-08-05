// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

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
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
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
            });
    });

    it('@base @order: can open and view nested line items in its modal', () => {
        const page = new OrderPageObject();

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Check if the labels are rendered correctly
        cy.get('.sw-data-grid__row--0 .sw-order-line-items-grid__item-label')
            .contains('LineItem 1');
        cy.get('.sw-data-grid__row--1 .sw-order-line-items-grid__item-label')
            .contains('LineItem 2');
        cy.get('.sw-data-grid__row--2 .sw-order-line-items-grid__item-label')
            .contains('LineItem 3');

        // Check if items with children have a button to toggle the nested line items modal
        cy.get('.sw-data-grid__row--0 .sw-order-line-items-grid__item-product')
            .children()
            .should('have.length', '2');
        cy.get('.sw-data-grid__row--1 .sw-order-line-items-grid__item-product')
            .children()
            .should('have.length', '2');
        cy.get('.sw-data-grid__row--2 .sw-order-line-items-grid__item-product')
            .children()
            .should('have.length', '1');

        // Check first nested line item modal
        cy.get('.sw-data-grid__row--0 .sw-order-line-items-grid__item-nested-indicator').click();

        // Check correct amount in each nesting level
        cy.get('.sw-modal__title').contains('Item: LineItem 1 - €0.01');
        cy.get('.nesting-level-1').should('have.length', '1');
        cy.get('.nesting-level-2').should('have.length', '3');
        cy.get('.nesting-level-3').should('have.length', '1');
        cy.get('.nesting-level-4').should('have.length', '1');
        cy.get('.nesting-level-5').should('not.exist');

        // Check the contents of the first row completely
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .get('.sw-order-nested-line-items-row__nesting-container')
            .contains('LineItem 1.1');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .get('.sw-order-nested-line-items-row__unit-price')
            .contains('€0.088');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .get('.sw-order-nested-line-items-row__quantity')
            .contains('110');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .get('.sw-order-nested-line-items-row__total-price')
            .contains('€0.11');
        cy.get('.sw-order-nested-line-items-row__content').eq('0')
            .get('.sw-order-nested-line-items-row__tax')
            .contains('20 %');

        // Check the contents of the third row with some values
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .get('.sw-order-nested-line-items-row__nesting-container')
            .contains('LineItem 1.1.1.1');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .get('.sw-order-nested-line-items-row__quantity')
            .contains('11110');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .get('.sw-order-nested-line-items-row__total-price')
            .contains('€11.11');

        // Close modal and check the second
        cy.get('.sw-modal__close').click();
        cy.get('.sw-data-grid__row--1 .sw-order-line-items-grid__item-nested-indicator').click();

        // Check correct amount in each nesting level
        cy.get('.sw-modal__title').contains('Item: LineItem 2 - €0.02');
        cy.get('.nesting-level-1').should('have.length', '2');
        cy.get('.nesting-level-2').should('have.length', '1');
        cy.get('.nesting-level-3').should('not.exist');

        // Check the contents of the third row with some values
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .get('.sw-order-nested-line-items-row__nesting-container')
            .contains('LineItem 2.2.1');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .get('.sw-order-nested-line-items-row__quantity')
            .contains('2210');
        cy.get('.sw-order-nested-line-items-row__content').eq('2')
            .get('.sw-order-nested-line-items-row__total-price')
            .contains('€2.21');
    });
});
