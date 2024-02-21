/// <reference types="Cypress" />
import AccountPageObject from '../../../../support/pages/account.page-object';
import ShippingPageObject from '../../../../support/pages/module/sw-shipping.page-object';
import CheckoutPageObject from '../../../../support/pages/checkout.page-object';
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

const page = new AccountPageObject();
const shippingPage = new ShippingPageObject();
const checkoutPage = new CheckoutPageObject();
const productPage = new ProductPageObject();

describe('Rule builder: Test with shipping method and advance pricing', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixtureStorefront();
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    // NEXT-33715 - Flaky
    it.skip('@package @rule: should use rule builder with the shipping method', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/rule`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/shipping-method`,
            method: 'POST',
        }).as('saveShipping');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('getSalesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/shipping-method`,
            method: 'POST',
        }).as('getShippingMethod');

        cy.url().should('include', 'settings/rule/index');
        cy.get('a[href="#/sw/settings/rule/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/rule/create/base');

        // fill basic data
        cy.get('input#sw-field--rule-name').clearTypeAndCheck('Shipping to Netherlands');
        cy.get('input#sw-field--rule-priority').clearTypeAndCheck('101');
        cy.get('.sw-settings-rule-detail__type-field .sw-select__selection').typeMultiSelectAndCheck('Shipping');

        // fill rule data
        cy.get('.sw-condition').then((conditionElement) => {
            cy.get('.sw-condition-type-select', {withinSubject: conditionElement})
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');

                    cy.get('.sw-select-result-list-popover-wrapper').contains('Shipping address: Country')
                        .click();
                });
            cy.get('.sw-condition-operator-select')
                .then((conditionOperatorSelect) => {
                    cy.wrap(conditionOperatorSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Is one of').click();
                });

            cy.get('.sw-condition .sw-entity-multi-select')
                .typeMultiSelectAndCheck('Netherlands', {
                    searchTerm: 'Netherlands',
                });
        });

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        // Create shipping method
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/shipping/index');
        cy.get('a[href="#/sw/settings/shipping/create"]').click();
        cy.get('input[name=sw-field--shippingMethod-name]').typeAndCheck('Shipping to Netherlands');
        cy.get('input[name=sw-field--shippingMethod-technicalName]').typeAndCheck('shipping_netherlands');
        cy.get('input[name=sw-field--shippingMethod-active]').check();
        cy.get('.sw-settings-shipping-detail__delivery-time').typeSingleSelectAndCheck(
            '1-3 days',
            '.sw-settings-shipping-detail__delivery-time',
        );
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelectAndCheck(
            'Shipping to Netherlands',
            '.sw-settings-shipping-detail__top-rule',
        );
        cy.get('.sw-settings-shipping__tax-type-selection').typeSingleSelectAndCheck(
            'Fixed',
            '.sw-settings-shipping__tax-type-selection',
        );
        cy.get('.sw-settings-shipping__tax-rate').should('exist');
        cy.get('.sw-settings-shipping__tax-rate').typeSingleSelectAndCheck(
            'Standard rate',
            '.sw-settings-shipping__tax-rate',
        );
        shippingPage.createShippingMethodPriceRule();
        cy.get(shippingPage.elements.shippingSaveAction).click();
        cy.wait('@saveShipping').its('response.statusCode').should('equal', 204);

        // Add shipping method to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'dashboard/index');
        cy.goToSalesChannelDetail('Storefront');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-sales-channel-detail__select-shipping-methods').scrollIntoView();
        cy.get('.sw-sales-channel-detail__select-shipping-methods .sw-select-selection-list__input').should('be.visible')
            .type('Shipping to Netherlands');
        cy.wait('@getShippingMethod');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').should('be.visible');
        cy.wait('@getShippingMethod');
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        // now it should be safe to select the element in the flyout
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0 .sw-highlight-text__highlight').contains('Shipping to Netherlands');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').click({
            force: true,
        });
        cy.get('.sw-sales-channel-detail__select-shipping-methods .sw-select-selection-list__item-holder').should('contain', 'Shipping to Netherlands');
        cy.get('.sw-sales-channel-detail__save-action').click();
        cy.wait('@getSalesChannel').its('response.statusCode').should('equal', 200);

        // Login from the storefront
        cy.visit('/account/login');
        cy.url().should('include', 'account/login');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();
        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        // Verify new created shipping method is not available
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');

        cy.get('.line-item-label').contains('Product name');
        cy.get('a[title="Go to checkout"]').click();
        cy.url().should('include', 'checkout/confirm');
        cy.get('.address').contains('Germany');
        cy.contains('Standard').should('exist');
        cy.contains('Express').should('exist');
        cy.contains('Shipping to Netherlands').should('not.exist');

        // Verify again Shipping to the Netherlands exist after changing the shipping address
        cy.contains('Change shipping address').click();

        // Verify address modal is shown
        cy.get('.address-editor-modal.show').should('be.visible');

        cy.contains('Edit address').click();

        // Verify address collapse is shown
        cy.get('#shipping-address-create-edit').should('be.visible');

        cy.get('#shipping-addressAddressCountry').first().select('Netherlands');
        cy.contains('Save address').click();

        // Verify address modal is closed again after saving the address
        cy.get('.address-editor-modal.show').should('be.visible');

        cy.url().should('include', 'checkout/confirm');
        cy.contains('Standard').should('exist');
        cy.contains('Express').should('exist');
        cy.contains('Shipping to Netherlands').should('exist');
    });

    it('@package @rule: should use rule builder with the advance pricing', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/rule`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        const advancePriceStandard = 45.98;
        const gridRowOne = '[class="sw-data-grid__row sw-data-grid__row--1"]';

        cy.url().should('include', 'settings/rule/index');
        cy.get('a[href="#/sw/settings/rule/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'settings/rule/create/base');

        // fill basic data
        cy.get('input#sw-field--rule-name').clearTypeAndCheck('Dollar currency');
        cy.get('input#sw-field--rule-priority').clearTypeAndCheck('5');
        cy.get('.sw-settings-rule-detail__type-field .sw-select__selection').typeMultiSelectAndCheck('Payment');

        // fill rule data
        cy.get('.sw-condition').then((conditionElement) => {
            cy.get('.sw-condition-type-select', {withinSubject: conditionElement})
                .then((conditionTypeSelect) => {
                    cy.wrap(conditionTypeSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');

                    cy.get('.sw-select-result-list-popover-wrapper').contains('Currency')
                        .click();
                });
            cy.get('.sw-condition-operator-select')
                .then((conditionOperatorSelect) => {
                    cy.wrap(conditionOperatorSelect).click();
                    cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
                    cy.get('.sw-select-result-list-popover-wrapper').contains('Is one of').click();
                });
            cy.get('.sw-condition .sw-entity-multi-select')
                .typeMultiSelectAndCheck('US-Dollar', {
                    searchTerm: 'US-Dollar',
                });
        });

        // Verify rule
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        // Select the rule as Dollar currency
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productPage.elements.contextMenuButton,
            `${productPage.elements.dataGridRow}--0`,
        );
        cy.get('.sw-product-detail__tab-advanced-prices').click();
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .typeSingleSelect('Dollar currency', '.sw-product-detail-context-prices__empty-state-select-rule');

        // Add advance pricing for the product
        cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
        cy.get('[placeholder="∞"').should('be.visible').clearTypeAndCheck('5');
        cy.get('[placeholder="∞"').type('{enter}');
        cy.get('.sw-data-grid__row--1').should('be.visible');
        cy.get('input#sw-price-field-net').first().should('have.value', '42');
        cy.get('.sw-data-grid__row.sw-data-grid__row--1 .sw-price-field__lock').first().click();
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().clearTypeAndCheck(advancePriceStandard);
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .type('{enter}');
        cy.get(`${gridRowOne} .sw-price-field__net [type]`).first()
            .should('have.value', '38.638655462185');
        cy.get(productPage.elements.productSaveAction).click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);

        // Verify from the storefront that advance pricing is not available since the currency is Euro
        cy.visit('/');
        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.contains('To 5').should('not.exist');
        cy.contains('From 6').should('not.exist');

        // Change currency to Dollar to see the advance pricing option
        cy.get('button#currenciesDropdown-top-bar').click();
        cy.contains('$ USD').click();
        cy.contains('To 5').should('exist');
        cy.contains('From 6').should('exist');
    });
});
