/// <reference types="Cypress" />
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const page = new ProductPageObject();
const checkoutPage = new CheckoutPageObject();
const promoCode = 'Flash sale';

describe('Create promotion codes to the product and check it at the storefront', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@package: should create promotion, configure conditions and discounts', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
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
        cy.contains('select#sw-field--selectedCodeType', 'Geen promotiecode vereist');

        // Configure Conditions
        cy.get('.sw-tabs-item[title="Voorwaarden"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');
        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck('E2E install test');
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
        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            // Off canvas verify promotion name and percentage
            cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
            cy.contains(`${lineItemSelector}-label`, 'Test Product');
            cy.contains(`${lineItemSelector}-promotion`, promoCode);
            cy.get(`${lineItemSelector}-promotion ${lineItemSelector}-price`)
                .should('include.text', '-6,00');
        });
    });

    it('@package: should create fixed promotion code and check from the storefront', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
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
        cy.get('#sw-field--selectedCodeType').select('Vaste promotie code');
        cy.get('input#sw-field--promotion-code').clearTypeAndCheck('EK_1907');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify promotion on detail page
        cy.get('#sw-field--promotion-name').should('have.value', promoCode);
        cy.get('input[name="sw-field--promotion-active"]').should('be.checked');
        cy.get('#sw-field--promotion-maxRedemptionsGlobal')
            .should('be.empty')
            .should('have.attr', 'placeholder', 'Onbeperkt');
        cy.contains('select#sw-field--selectedCodeType', 'Vaste promotie code');

        // Configure Conditions
        cy.get('.sw-tabs-item[title="Voorwaarden"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');
        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck('E2E install test');
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
        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            // Off canvas verify promotion name and percentage
            cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
            cy.contains(`${lineItemSelector}-label`, 'Test Product');
            cy.get('#addPromotionOffcanvasCartInput').clearTypeAndCheck('EK_1907');
            cy.get('button#addPromotionOffcanvasCart').click();
            cy.get('.sw-loader').should('not.exist');
            cy.contains(`${lineItemSelector}-promotion`, promoCode);
            cy.get(`${lineItemSelector}-promotion ${lineItemSelector}-price`)
                .should('include.text', '-6,00');
        });
    });

    it('@package: should create individual promotion codes and check from the storefront', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('saveProduct');

        const gridCell = '.sw-data-grid__cell';
        const dataRowZero = '.sw-data-grid__row.sw-data-grid__row--0';

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

        cy.get('#sw-field--selectedCodeType').select('Individuele promotie codes');
        cy.contains('Generate new codes').click();
        cy.get('#modalTitleEl').should('be.visible');
        cy.get('input#sw-field--pattern-prefix').clearTypeAndCheck('EK_');
        cy.get('input#sw-field--preview').should('have.attr', 'label', 'Voorbeeld promotieco');
        cy.get('.sw-promotion-v2-generate-codes-modal__button-generate').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify promotion on detail page
        cy.get('#sw-field--promotion-name').should('have.value', promoCode);
        cy.get('input[name="sw-field--promotion-active"]').should('be.checked');
        cy.get('#sw-field--promotion-maxRedemptionsGlobal')
            .should('be.empty')
            .should('have.attr', 'placeholder', 'Onbeperkt');
        cy.contains('select#sw-field--selectedCodeType', 'Individuele promotie codes');

        // Configure Conditions
        cy.get('.sw-tabs-item[title="Voorwaarden"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');
        cy.get('.sw-promotion-v2-conditions__sales-channel-selection')
            .typeMultiSelectAndCheck('E2E install test');
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
        cy.get('.sw-card--hero button').click();
        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('#sw-field--discount-scope').select('Winkelmandje');
        cy.get('#sw-field--discount-type').select('Procentueel');
        cy.get('.sw-promotion-discount-component__discount-value input').clearTypeAndCheck('10');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-tabs-item[title="Generaal"]')
            .should('not.have.class', 'sw-tabs-item--active')
            .click()
            .should('have.class', 'sw-tabs-item--active');

        cy.get(`${dataRowZero} ${gridCell}-content ${gridCell}-value`).then(($span) => {
            const individualPromoCode = $span.text().trim();

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

            // check from the store front
            cy.visit('/');
            cy.contains('Home');
            cy.get('.header-search-input')
                .should('be.visible')
                .type('Test Product');
            cy.contains('.search-suggest-product-name', 'Test Product').click();
            cy.get('.product-detail-buy .btn-buy').click();

            cy.window().then((win) => {
                /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
                const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

                // Off canvas verify promotion name and percentage
                cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
                cy.contains(`${lineItemSelector}-label`, 'Test Product');
                cy.get('#addPromotionOffcanvasCartInput').clearTypeAndCheck(individualPromoCode);
                cy.get('button#addPromotionOffcanvasCart').click();
                cy.get('.sw-loader').should('not.exist');
                cy.contains(`${lineItemSelector}-promotion`, promoCode);
                cy.get(`${lineItemSelector}-promotion ${lineItemSelector}-price`)
                    .should('include.text', '-6,00');
            });
        });
    });
 });
