// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

function navigateToOrder(page) {
    cy.route({
        url: `${Cypress.env('apiPath')}/_action/version/order/**`,
        method: 'post'
    }).as('orderEditCall');

    cy.route({
        url: `${Cypress.env('apiPath')}/_action/version/merge/order/**`,
        method: 'post'
    }).as('orderSaveCall');

    cy.route({
        url: `${Cypress.env('apiPath')}/_action/order/**/product/**`,
        method: 'post'
    }).as('orderAddProductCall');

    cy.route({
        url: `${Cypress.env('apiPath')}/_action/order/**/recalculate`,
        method: 'post'
    }).as('orderRecalculateCall');

    cy.route({
        url: `${Cypress.env('apiPath')}/order-line-item/**`,
        method: 'delete'
    }).as('deleteLineItemCall');

    cy.clickContextMenuItem(
        '.sw-order-list__order-view-action',
        page.elements.contextMenuButton,
        `${page.elements.dataGridRow}--0`
    );

    // edit order
    cy.get('.sw-order-detail__smart-bar-edit-button').click();

    cy.wait('@orderEditCall').then((xhr) => {
        expect(xhr).to.have.property('status', 200);
    });
}

/**
 * Asserts that the price breakdown contains a given row title and optionally a given content for that title
 * @param {string|RegExp} title
 * @param {string|RegExp|null} [content=null]
 */
function assertPriceBreakdownContains(title, content = null) {
    const table = cy.get('.sw-order-detail__summary-data');
    const titleElem = table.children('dt').contains(title);
    if (content !== null) {
        titleElem.then(($elem) => {
            if ($elem.prop('tagName') === 'STRONG') {
                titleElem.parent().next().children('strong').contains(content);
            } else {
                titleElem.next().contains(content);
            }
        });
    }
}

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
                return cy.createProductFixture({
                    name: 'Awesome product',
                    productNumber: 'RS-1337',
                    description: 'l33t',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            net: 24,
                            linked: false,
                            gross: 128
                        }
                    ]
                });
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

    it('@base @order: can add existing product', () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

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

        // assert save successful
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content').contains('2');
    });

    it('@base @order: can add new products', () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        // click "add product"
        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        cy.get('.sw-order-line-items-grid__actions-container-add-product-btn').click();

        // select product
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label').dblclick();

        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Awesome product', '.sw-order-product-select__single-select');
        cy.get('#sw-field--item-quantity').clear().type('010');

        cy.get('.sw-data-grid__inline-edit-save').click();
        cy.wait('@orderAddProductCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // click save
        cy.get('.sw-order-detail__smart-bar-save-button').click();

        // assert save successful
        cy.wait('@orderSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Get correct quantity of both items
        cy.get('.sw-data-grid__row--1')
            .within(() => {
                cy.get('.sw-data-grid__cell--quantity .sw-data-grid__cell-content').contains('10');
            });

        cy.get('.sw-data-grid__row--0')
            .within(() => {
                cy.get('.sw-data-grid__cell--quantity .sw-data-grid__cell-content').contains('1');
            });
    });

    it('@base @order: can add custom products', () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        // click "add custom product"

        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-line-items-grid__actions-container .sw-button-group .sw-context-button',
            null,
            'Add custom item'
        );

        // enter edit state
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label').dblclick().click();

        // enter item name ...
        cy.get('#sw-field--item-label').type('wusel');
        // ... price ...
        cy.get('#sw-field--item-priceDefinition-price').clear().type('1337');
        // ... quantity ...
        // cy.get('#sw-field--item-quantity').clear().type('10');
        cy.get('#sw-field--item-quantity').clear().type('010');
        // ... and tax rate
        cy.get('#sw-field--item-priceDefinition-taxRules\\[0\\]-taxRate').clear().type('10');
        // save line item
        cy.get('.sw-data-grid__inline-edit-save').click();

        cy.wait('@orderRecalculateCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // click save
        cy.get('.sw-order-detail__smart-bar-save-button').click();

        cy.wait('@orderSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Assert the price breakdown contains both VATs. This also implies that a recalculation has taken place.
        assertPriceBreakdownContains(/^\s*plus 19\% VAT\s*$/, /^\s*€.[0-9]+\.[0-9]{2}\s*$/);
        assertPriceBreakdownContains(/^\s*plus 10\% VAT\s*$/, /^\s*€1,215\.45\s*$/);
    });

    it('@base @order: can add custom credit items', () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        // click "add custom product"

        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-line-items-grid__actions-container .sw-button-group .sw-context-button',
            null,
            'Add credit'
        );

        // enter edit state
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--label').dblclick().click();

        // enter item name ...
        cy.get('#sw-field--item-label').type('wusel');
        // ... and discout
        cy.get('#sw-field--item-priceDefinition-price').clear().type('-133333337');
        // save line item
        cy.get('.sw-data-grid__inline-edit-save').click();

        cy.wait('@orderRecalculateCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // click save
        cy.get('.sw-order-detail__smart-bar-save-button').click();

        cy.wait('@orderSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Assert that the total is negative
        assertPriceBreakdownContains(/^\s*Total including VAT\s*$/, /^\s*-€[0-9,]+.[0-9]{2}\s*$/);
    });

    it('@base @order: can delete items', () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();
        cy.get('.sw-order-detail-base__line-item-grid-card').within(() => {
            // assert that one row exists
            cy.get('.sw-data-grid__body').children().should('have.length', 1);

            // delete the only item
            cy.get('.sw-data-grid__select-all').click();
            cy.get('.sw-data-grid__bulk').within(() => {
                cy.get('.link').click();
            });

            cy.wait('@deleteLineItemCall').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });
        });

        // click save
        cy.get('.sw-order-detail__smart-bar-save-button').click();

        cy.wait('@orderSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // assert that the item is still gone after saving
        cy.get('.sw-order-detail-base__line-item-grid-card').within(() => {
            cy.get('.sw-data-grid__body').children().should('have.length', 0);
        });
    });

    it('@base @order: can edit existing line items', () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get('.sw-order-detail-base__line-item-grid-card').scrollIntoView();

        // enter edit state
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--unitPrice').dblclick().click();

        // change item price ...
        cy.get('#sw-field--item-priceDefinition-price').clear().type('1337');
        // ... quantity ...
        cy.get('#sw-field--item-quantity').clear().type('010');
        // ... and tax rate
        cy.get('#sw-field--item-priceDefinition-taxRules\\[0\\]-taxRate').clear().type('10');
        // save line item
        cy.get('.sw-data-grid__inline-edit-save').click();

        cy.wait('@orderRecalculateCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // click save
        cy.get('.sw-order-detail__smart-bar-save-button').click();

        cy.wait('@orderSaveCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // check that the changes have been persisted
        // currency and formatting independently regex for the price
        cy.get('.sw-data-grid__cell--unitPrice').contains(/1[,.]?337/);

        cy.get('.sw-data-grid__cell--quantity').contains('10');
        cy.get('.sw-data-grid__cell--price-taxRules\\[0\\]').contains(/10\s%/);

        // currency and formatting independently regex for the price
        assertPriceBreakdownContains(/^\s*plus 10% VAT\s*$/, /1[,.]?215[.,]45/);
    });
});
