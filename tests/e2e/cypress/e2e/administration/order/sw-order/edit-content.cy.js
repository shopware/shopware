// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

function navigateToOrder(page) {
    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/_action/version/order/**`,
        method: 'POST',
    }).as('orderEditCall');

    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/_action/version/merge/order/**`,
        method: 'POST',
    }).as('orderSaveCall');

    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/_action/order/**/product/**`,
        method: 'POST',
    }).as('orderAddProductCall');

    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/_action/order/**/recalculate`,
        method: 'POST',
    }).as('orderRecalculateCall');

    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/order-line-item/**`,
        method: 'DELETE',
    }).as('deleteLineItemCall');

    cy.intercept({
        url: `**/${Cypress.env('apiPath')}/search/order`,
        method: 'POST',
    }).as('orderSearchCall');

    cy.clickContextMenuItem(
        '.sw-order-list__order-view-action',
        page.elements.contextMenuButton,
        `${page.elements.dataGridRow}--0`,
    );

    cy.wait('@orderEditCall').its('response.statusCode').should('equal', 200);
}

/**
 * Asserts that the price breakdown contains a given row title and optionally a given content for that title
 * @param {string|RegExp} title
 * @param {string|RegExp|null} [content=null]
 */
function assertPriceBreakdownContains(title, content = null) {
    if (content !== null) {
        cy.get('.sw-order-detail__summary').children('dt').contains(title).then(($elem) => {
            if ($elem.prop('tagName') === 'STRONG') {
                cy.get('.sw-order-detail__summary')
                    .children('dt')
                    .contains(title)
                    .parent()
                    .next()
                    .children('strong')
                    .contains(content);
            } else {
                cy.get('.sw-order-detail__summary').children('dt').contains(title).next().contains(content);
            }
        });
    }
}

describe('Order: Read order', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createProductFixture({
                name: 'Awesome product',
                productNumber: 'RS-1337',
                description: 'l33t',
                price: [
                    {
                        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                        net: 24,
                        linked: false,
                        gross: 128,
                    },
                ],
            });
        })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name',
                    },
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

    it('@base @order: can add existing product', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        cy.get(page.elements.tabs.general.addProductButton).click();

        cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--label`).dblclick();
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-order-product-select__single-select');

        cy.get(page.elements.dataGridInlineEditSave).click();
        cy.wait('@orderAddProductCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarSave).click();
        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);

        cy.contains(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content`,
            '2');
    });

    it('@base @order: can add new products', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        cy.get(page.elements.tabs.general.addProductButton).click();

        cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--label`).dblclick();
        cy.get('.sw-order-product-select__single-select')
            .typeSingleSelectAndCheck('Awesome product', '.sw-order-product-select__single-select');

        cy.get('#sw-field--item-quantity').clear().type('010');

        cy.get(page.elements.dataGridInlineEditSave).click();
        cy.wait('@orderAddProductCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarSave).click();
        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);

        cy.get(`${page.elements.dataGridRow}--1`)
            .within(() => {
                cy.contains('.sw-data-grid__cell--quantity .sw-data-grid__cell-content', '10');
            });

        cy.get(`${page.elements.dataGridRow}--0`)
            .within(() => {
                cy.contains('.sw-data-grid__cell--quantity .sw-data-grid__cell-content', '1');
            });
    });

    it('@base @order: can add custom products', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-line-items-grid__actions-container .sw-button-group .sw-context-button',
            null,
            'Add custom item',
        );

        cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--label`).dblclick().click();

        // enter item name ...
        cy.get('#sw-field--item-label').type('wusel');
        // ... price ...
        cy.get('#sw-field--item-priceDefinition-price').clear().type('1337');
        // ... quantity ...
        cy.get('#sw-field--item-quantity').clear().type('010');
        // ... and tax rate
        cy.get('#sw-field--item-priceDefinition-taxRules\\[0\\]-taxRate').clear().type('10');

        cy.get(page.elements.dataGridInlineEditSave).click();
        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarSave).click();
        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        // Assert the price breakdown contains both VATs. This also implies that a recalculation has taken place.
        assertPriceBreakdownContains(/^\s*plus 19% VAT\s*$/, /^\s*€[0-9]+\.[0-9]{2}\s*$/);
        assertPriceBreakdownContains(/^\s*plus 10% VAT\s*$/, /^\s*€1,215\.45\s*$/);
    });

    it('@base @order: can add custom credit items', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        cy.clickContextMenuItem(
            '.sw-context-menu-item',
            '.sw-order-line-items-grid__actions-container .sw-button-group .sw-context-button',
            null,
            'Add credit',
        );

        cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--label`).dblclick().click();

        // enter item name ...
        cy.get('#sw-field--item-label').type('wusel');
        // ... and discout
        cy.get('#sw-field--item-priceDefinition-price').clear().type('-133333337');

        cy.get(page.elements.dataGridInlineEditSave).click();
        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarSave).click();
        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);

        // Assert that the total is negative
        assertPriceBreakdownContains(/^\s*Total including VAT\s*$/, /^\s*-€[0-9,]+.[0-9]{2}\s*$/);
    });

    it('@base @order: can delete multiple items', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView().within(() => {
            // assert that one row exists
            cy.get('.sw-data-grid__body').children().should('have.length', 1);

            // delete the only item
            cy.get('.sw-data-grid__select-all').click();
            cy.get('.sw-data-grid__bulk').within(() => {
                cy.get('.link').click();
            });

            cy.wait('@deleteLineItemCall').its('response.statusCode').should('equal', 204);
        });

        // click save
        cy.get(page.elements.smartBarSave).click();

        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);

        // assert that the item is still gone after saving
        cy.get(`${page.elements.tabs.general.gridCard} .sw-data-grid__body`).children().should('have.length', 0);
    });

    it('@base @order: can delete single item', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();
        cy.get(`${page.elements.tabs.general.gridCard} .sw-data-grid__body`).children().should('have.length', 1);

        // delete the only item
        cy.clickContextMenuItem(
            '.sw-context-menu__content',
            page.elements.contextMenuButton,
            page.elements.tabs.general.gridCard,
            'Remove from order',
        );

        cy.get(`${page.elements.modal} ${page.elements.dangerButton}`).click();

        cy.wait('@deleteLineItemCall').its('response.statusCode').should('equal', 204);

        // click save
        cy.get(page.elements.smartBarSave).click();

        cy.wait('@orderSaveCall').its('response.statusCode').should('equal', 204);

        // assert that the item is still gone after saving
        cy.get(`${page.elements.tabs.general.gridCard} .sw-data-grid__body`).children().should('have.length', 0);
    });

    it('@base @order: can edit existing line items', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new OrderPageObject();

        navigateToOrder(page);

        cy.get(page.elements.tabs.general.gridCard).scrollIntoView();

        cy.get(`${page.elements.dataGridRow}--0 > .sw-data-grid__cell--unitPrice`).dblclick();

        // change item price ...
        cy.get('#sw-field--item-priceDefinition-price').clear().type('1337');
        // ... quantity ...
        cy.get('#sw-field--item-quantity').clear().type('010');
        // ... and tax rate
        cy.get('#sw-field--item-priceDefinition-taxRules\\[0\\]-taxRate').clear().type('10');
        // save line item
        cy.get(page.elements.dataGridInlineEditSave).click();

        cy.wait('@orderRecalculateCall').its('response.statusCode').should('equal', 204);
        cy.wait('@orderSearchCall').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarSave).click();

        // check that the changes have been persisted
        // currency and formatting independently regex for the price
        cy.contains('.sw-data-grid__cell--unitPrice', /1[,.]?337/);
        cy.contains('.sw-data-grid__cell--quantity', '10');
        cy.contains('.sw-data-grid__cell--price-taxRules\\[0\\]', /10\s%/);

        // currency and formatting independently regex for the price
        assertPriceBreakdownContains(/^\s*plus 10% VAT\s*$/, /1[,.]?215[.,]45/);
    });
});
