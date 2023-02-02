/// <reference types="Cypress" />
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const page = new ProductPageObject();
const checkoutPage = new CheckoutPageObject();

describe('Promotions: rule based conditions & Rule Builder', () => {
    beforeEach(() => {
        cy.loginViaApi().then(() => {
            cy.createProductFixture({
                name: 'Test Product',
                productNumber: 'Test-3096',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    linked: true,
                    gross: 60
                }]
            });
        }).then(() => {
            return cy.createDefaultFixture('promotion');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: should set a rule based conditions to the promotion and check it in the storefront', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');

        cy.url().should('include', 'promotion/v2/index');
        cy.contains('Thunder Tuesday').click();
        cy.url().should('include', 'promotion/v2/detail');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify promotion on detail page
        cy.get('#sw-field--promotion-name').should('have.value', 'Thunder Tuesday');
        cy.get('input[name="sw-field--promotion-active"]').should('be.checked');
        cy.get('#sw-field--promotion-maxRedemptionsGlobal')
            .should('be.empty')
            .should('have.attr', 'placeholder', 'Onbeperkt');
        cy.contains('select#sw-field--selectedCodeType', 'Geen promotiecode vereist');

        // Set a rule based conditions to promotion to the product
        cy.get('.sw-tabs-item[title="Voorwaarden"]').click();
        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck('E2E install test');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer')
            .typeMultiSelectAndCheck('All customers');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer').type('{esc}');
        cy.get('.sw-promotion-v2-cart-condition-form__rule-select-cart')
            .type('Nieuwe regel aanmaken');
        cy.contains('Nieuwe regel aanmaken').click();
        cy.get('input#sw-field--rule-name').clearTypeAndCheck('Price of Orders');
        cy.get('input#sw-field--rule-priority').clearTypeAndCheck('1');

        cy.get('.sw-condition').then((conditionElement) => {
            cy.get('.sw-condition-type-select', {withinSubject: conditionElement})
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Subtotaal van alle items')
                        .click();
                });
        });
        cy.get('.is--placeholder.sw-single-select__selection-text').type('Groter dan');
        cy.get('.is--active').contains('Groter dan').click();
        cy.get('#sw-field--amount').clearTypeAndCheck('500');
        cy.get('.sw-rule-modal__save > .sw-button__content').click();
        cy.get('.sw-promotion-v2-conditions__rule-select-order-conditions')
            .typeMultiSelectAndCheck('All customers');

        // Configure Discounts
        cy.get('.sw-tabs-item[title="Kortingen"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');
        cy.get('.sw-promotion-discount-component').should('not.exist');
        cy.contains('Korting toevoegen').should('exist');
        cy.get('.sw-card--hero button').click();
        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('#sw-field--discount-scope').select('Winkelmandje');
        cy.get('#sw-field--discount-type').select('Procentueel');
        cy.get('.sw-promotion-discount-component__discount-value input').clearTypeAndCheck('10');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains('h2', 'Test Product').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck('E2E install test');
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Check from the store front
        cy.visit('/');

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            cy.contains('Home');
            cy.get('.header-search-input')
                .should('be.visible')
                .type('Test Product');
            cy.contains('.search-suggest-product-name', 'Test Product').click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas, verify promotion is not available
            cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
            cy.contains(`${lineItemSelector}-label`, 'Test Product');
            cy.contains('Thunder Tuesday').should('not.exist');
            cy.get('.summary-value.summary-total').should('include.text', '60,00');

            // Set the product number to 10, price over 500€ and verify promo code is visible
            cy.get(`${lineItemSelector}-quantity-container > .custom-select`).select('10');
            cy.contains('Thunder Tuesday').should('exist');
            cy.get('.summary-value.summary-total').should('include.text', '540,00');
        });
    });
});
