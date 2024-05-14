/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const page = new ProductPageObject();
const checkoutPage = new CheckoutPageObject();
const promoCode = 'Flash sale';

describe('Promotions: pre-conditions', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Test Product',
            productNumber: 'Test-3096',
            price: [{
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                linked: true,
                gross: 60,
            }],
        }).then(() => {
            return cy.createDefaultFixture('promotion');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: should create promotion, configure conditions and discounts', { tags: ['pa-checkout', 'quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('saveProduct');

        cy.url().should('include', 'promotion/v2/index');
        cy.get('.sw-promotion-v2-list__smart-bar-button-add').click();

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck(promoCode);
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@savePromotion').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify promotion on detail page
        cy.get('#sw-field--promotion-name').should('have.value', promoCode);
        cy.get('input[name="sw-field--promotion-active"]').should('be.checked');
        cy.get('#sw-field--promotion-maxRedemptionsGlobal')
            .should('be.empty')
            .should('have.attr', 'placeholder', 'Onbeperkt');
        cy.contains('#sw-field--selectedCodeType', 'Geen promotiecode vereist');

        // Configure Conditions
        cy.get('.sw-tabs-item[title="Voorwaarden"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');

        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.sw-promotion-v2-conditions__rule-select-customer')
            .typeMultiSelectAndCheck('All customers');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer').type('{esc}');
        cy.get('.sw-promotion-v2-cart-condition-form__rule-select-cart')
            .typeMultiSelectAndCheck('Always valid (Default)');
        cy.get('.sw-promotion-v2-conditions__rule-select-order-conditions')
            .typeMultiSelectAndCheck('All customers');

        // Configure Discounts
        cy.get('.sw-tabs-item[title="Kortingen"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');

        cy.get('.sw-promotion-discount-component').should('not.exist');
        cy.contains('Korting toevoegen').should('exist');
        cy.get('.promotion-detail-discounts__action_add button').click();
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
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('h2', 'Test Product').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Set the second promotion
        cy.visit(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'promotion/v2/index');
        cy.contains('Thunder Tuesday').click();
        cy.url().should('include', 'promotion/v2/detail');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Configure Conditions
        cy.get('.sw-tabs-item[title="Voorwaarden"]').click();
        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));
        cy.get('.sw-promotion-v2-conditions__rule-select-customer')
            .typeMultiSelectAndCheck('All customers');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer').type('{esc}');
        cy.get('.sw-promotion-v2-cart-condition-form__rule-select-cart')
            .typeMultiSelectAndCheck('Always valid (Default)');
        cy.get('.sw-promotion-v2-conditions__rule-select-order-conditions')
            .typeMultiSelectAndCheck('All customers');

        cy.get('.sw-tabs-item[title="Kortingen"]').click();
        cy.get('.sw-promotion-discount-component').should('not.exist');
        cy.contains('Korting toevoegen').should('exist');
        cy.get('.promotion-detail-discounts__action_add button').click();
        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('#sw-field--discount-scope').select('Winkelmandje');
        cy.get('#sw-field--discount-type').select('Procentueel');
        cy.get('.sw-promotion-discount-component__discount-value input').clearTypeAndCheck('5');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Check from the Storefront
        cy.visit('/');

        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas, verify both promotions are added to cart
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');
        cy.contains(promoCode).should('exist');
        cy.contains('Thunder Tuesday').should('exist');
        cy.get('.summary-value.summary-total').should('include.text', '51,00');

        // Set promotion settings to 'Prevent combination with other promotions'
        cy.visit(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'promotion/v2/index');
        cy.contains('Thunder Tuesday').click();
        cy.url().should('include', 'promotion/v2/detail');
        cy.get('.sw-tabs-item[title="Voorwaarden"]').click();
        cy.contains('Pre-voorwaarden').should('exist');
        cy.get('.sw-promotion-v2-conditions__prevent-combination [type]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Check from the store front
        cy.visit('/');
        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas, verify the second promotion is added to cart since it has prevent combination setting
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');
        cy.contains(promoCode).should('not.exist');
        cy.contains('Thunder Tuesday').should('exist');
        cy.get('.summary-value.summary-total').should('include.text', '114,00');
    });
});
