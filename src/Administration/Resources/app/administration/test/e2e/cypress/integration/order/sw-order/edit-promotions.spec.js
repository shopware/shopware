// / <reference types="Cypress" />

import OrderPageObject from '../../../support/pages/module/sw-order.page-object';

let salesChannelId;

describe('Order: Test promotions in existing orders', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
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
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        value: 'Storefront'
                    }
                });
            })
            .then((data) => {
                salesChannelId = data.id;

                cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
            });
    });

    it('@base @order: add promotion to existing order', () => {
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');

        const page = new OrderPageObject();

        cy.createDefaultFixture('promotion', {
            name: 'GET5',
            useCodes: true,
            code: 'GET5',
            active: true,
            salesChannels: [{
                salesChannelId: salesChannelId,
                priority: 1
            }],
            discounts: [{
                scope: 'cart',
                type: 'absolute',
                value: 5.0,
                considerAdvancedRules: false
            }]
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('orderCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/order/**/promotion-item`,
            method: 'POST'
        }).as('orderAddPromotionCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/order/**/`,
            method: 'POST'
        }).as('orderRemovePromotionCall');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        cy.contains('Edit')
            .click();

        cy.wait('@orderCall')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-data-grid__row--0').scrollIntoView();

        cy.get('input[name="sw-field--disabledAutoPromotionVisibility"]').should('be.checked');

        cy.get('.sw-tagged-field__input')
            .click()
            .typeAndCheck('GET5')
            .type('{enter}');

        cy.wait('@orderAddPromotionCall')
    .its('response.statusCode').should('equal', 200);
        cy.awaitAndCheckNotification('Discount GET5 has been added');

        cy.get('.sw-data-grid__row--1')
            .scrollIntoView()
            .contains('GET5');
    });

    it('@base @order: add automatic promotion to existing order', () => {
        // skip for feature FEATURE_NEXT_7530, this test is reactivated again with NEXT-16682
        cy.skipOnFeature('FEATURE_NEXT_7530');
        const page = new OrderPageObject();

        cy.createDefaultFixture('promotion', {
            name: 'Automatic promotion',
            useCodes: false,
            active: true,
            salesChannels: [{
                salesChannelId: salesChannelId,
                priority: 1
            }],
            discounts: [{
                scope: 'cart',
                type: 'absolute',
                value: 5.0,
                considerAdvancedRules: false
            }]
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('orderCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/order/**/toggleAutomaticPromotions`,
            method: 'POST'
        }).as('toggleAutomaticPromotionsCall');

        cy.get(`${page.elements.dataGridRow}--0`).contains('Mustermann, Max');
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.userMetadata}-user-name`)
            .contains('Max Mustermann');

        cy.contains('Edit')
            .click();

        cy.wait('@orderCall').its('response.statusCode').should('equal', 200);

        cy.get('input[name="sw-field--disabledAutoPromotionVisibility"]').should('be.checked');

        cy.get('.sw-order-detail-summary__switch-promotions')
            .scrollIntoView()
            .click();

        cy.wait('@toggleAutomaticPromotionsCall')
    .its('response.statusCode').should('equal', 200);
        cy.awaitAndCheckNotification('Discount Automatic promotion has been added');

        cy.get('.sw-data-grid__row--1')
            .scrollIntoView()
            .contains('Automatic promotion');

        cy.get('input[name="sw-field--disabledAutoPromotionVisibility"]').should('not.be.checked');

        cy.get('.sw-order-detail-summary__switch-promotions')
            .click();

        cy.wait('@toggleAutomaticPromotionsCall')
    .its('response.statusCode').should('equal', 200);

        cy.awaitAndCheckNotification('Discount Automatic promotion has been removed');
        cy.get('input[name="sw-field--disabledAutoPromotionVisibility"]').should('be.checked');
    });
});
