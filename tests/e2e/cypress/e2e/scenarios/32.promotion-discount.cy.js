/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const page = new ProductPageObject();
const checkoutPage = new CheckoutPageObject();
const promoCode = 'Flash sale';

describe('Promotions: Discount for a specific range of products', { tags: ['pa-checkout'] }, () => {
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
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: should create promotion and apply it for custom products, based on amount', {tags: ['quarantined']}, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
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

        // Set promote set of products
        cy.get('[name="sw-field--promotion-useSetGroups"]').click();
        cy.contains('Product set toevoegen').should('exist');
        cy.contains('Product set toevoegen').click();
        cy.get('.sw-promotion-v2-cart-condition-form__setgroup-card-title').should('be.visible');
        cy.contains('select#sw-field--group-packagerKey', 'hoeveelheid');
        cy.get('input#sw-field--group-value').clearTypeAndCheck('5');
        cy.contains('select#sw-field--group-sorterKey', 'Prijs, oplopend');
        cy.get('[label="Product regels"]').typeMultiSelectAndCheck('All customers');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

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

        // Check from the Storefront
        cy.visit('/');

        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas, verify promo code is not available since 1 product added to card
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');
        cy.contains(promoCode).should('not.exist');
        cy.get('.summary-value.summary-total').should('include.text', '60,00');

        // Set the product number to 5 and verify promo code is visible and %10 discount is applied to the card
        cy.get('.line-item-quantity-group > .form-control').clear().type('5{enter}');
        cy.contains(promoCode).should('exist');
        cy.get('.summary-value.summary-total').should('include.text', '270,00');
    });

    // TODO: needs to be fixed for sw-promotion-v2-discounts
    it.skip('@package: should create promotion and apply it for custom products based on price', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        cy.url().should('include', 'promotion/v2/index');
        cy.get('.sw-promotion-v2-list__smart-bar-button-add').click();

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck(promoCode);
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
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

        // Set promote set of products
        cy.get('[name="sw-field--promotion-useSetGroups"]').click();
        cy.contains('Product set toevoegen').should('exist');
        cy.contains('Product set toevoegen').click();
        cy.get('.sw-promotion-v2-cart-condition-form__setgroup-card-title').should('be.visible');
        cy.get('select#sw-field--group-packagerKey').select('Bedrag (bruto)');
        cy.get('input#sw-field--group-value').clearTypeAndCheck('100');
        cy.contains('select#sw-field--group-sorterKey', 'Prijs, oplopend');
        cy.get('[label="Product regels"]').typeMultiSelectAndCheck('All customers');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

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
        cy.wait('@savePromotion').its('response.statusCode').should('equal', 204);
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

        // Check from the Storefront
        cy.visit('/');

        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Verify promo code is NOT available since the product price is 60€, which is under 100€
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');
        cy.contains(promoCode).should('not.exist');
        cy.get('.summary-value.summary-total').should('include.text', '60,00');

        // Set the product number to 2 and verify promo code is visible, and %10 discount is applied to the card
        cy.get('.line-item-quantity-group > .form-control').clear().type('2{enter}');
        cy.contains(promoCode).should('exist');
        cy.get('.summary-value.summary-total').should('include.text', '108,00');
    });

    // TODO: needs to be fixed for sw-promotion-v2-discounts
    it.skip('@package: should create promotion and apply to a specific range of products only', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
        }).as('savePromotion');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        cy.url().should('include', 'promotion/v2/index');
        cy.get('.sw-promotion-v2-list__smart-bar-button-add').click();

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck(promoCode);
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
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
            .typeMultiSelectAndCheck('Customers from USA');
        cy.get('.sw-promotion-v2-conditions__rule-select-customer').type('{esc}');
        cy.get('.sw-promotion-v2-cart-condition-form__rule-select-cart')
            .typeMultiSelectAndCheck('Always valid (Default)');
        cy.get('.sw-promotion-v2-conditions__rule-select-order-conditions')
            .typeMultiSelectAndCheck('Customers from USA');

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

        // Set the "apply to a specific range of products only" active
        cy.contains('Alleen op geselecteerde producten toepassen').click();
        cy.get('.sw-entity-many-to-many-select').typeMultiSelectAndCheck('Customers from USA');
        cy.get('select#sw-field--discount-applierKey').select('1. item');
        cy.get('#sw-field--discount-type').select('Absoluut');
        cy.get('.sw-promotion-discount-component__discount-value input').clearTypeAndCheck('20');
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@savePromotion').its('response.statusCode').should('equal', 204);
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

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'dashboard/index');
        cy.goToSalesChannelDetail(Cypress.env('storefrontName'))
            .selectCountryForSalesChannel('USA');

        // Login from the storefront as a customer from USA
        cy.visit('/account/login');

        cy.url().should('include', '/account/login');
        cy.get('#personalSalutation').select('Mrs.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Lisa');
        cy.get('#personalLastName').typeAndCheckStorefront('Hoffmann');
        cy.get('#personalMail').typeAndCheckStorefront('lisa@hoffmann.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Alabama street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('New Jersey');
        cy.get('#billingAddressAddressCountry').select('USA');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Check from the Storefront
        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Verify promo code is applied to the product, which should reduce the price
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');
        cy.contains(promoCode).should('exist');
        cy.get('.summary-value.summary-total').should('include.text', '40,00');

        // Set the product number to 2 and verify promotion is not applied to second product
        cy.get('.line-item-quantity-group > .form-control').clear().type('2{enter}');
        cy.contains(promoCode).should('exist');
        cy.get('.summary-value.summary-total').should('include.text', '100,00');
    });
});
