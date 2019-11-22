// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Promotion: Test promotion with codes', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('promotion');
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createCustomerFixture()
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/index`);
            });
    });

    it('@p @marketing: use general promotion codes', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/promotion',
            method: 'post'
        }).as('saveData');
        cy.route({
            url: '/api/v1/search/promotion/**/discounts',
            method: 'post'
        }).as('saveDiscount');

        // Active code in promotion
        cy.contains(`${page.elements.dataGridRow}--0 a`, 'Thunder Tuesday').click();
        cy.get('#sw-field--promotion-active').should('be.visible');
        cy.get('#sw-field--promotion-active').click();
        cy.get('.sw-promotion-sales-channel-select').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-sales-channel-select .sw-select-selection-list__input')
            .type('{esc}');
        cy.get('#sw-field--promotion-useCodes').click();
        cy.get('#sw-field--promotion-code').should('be.enabled');
        cy.get('#sw-field--promotion-code').type('funicular');

        // Add discount
        cy.get('a[title="Discounts"]').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-button--ghost').should('be.visible');
        cy.contains('.sw-button--ghost', 'Add discount').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.wait('@filteredResultCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .clear()
            .type('54');

        // Save final promotion
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@saveDiscount').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Verify Promotion in Storefront
        cy.visit('/');
        cy.get('.product-box').should('be.visible');
        cy.get('.btn-buy').click();
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('#addPromotionOffcanvasCartInput').type('funicular');
        cy.get('#addPromotionOffcanvasCart').click();
        cy.get('.alert-success .icon-checkmark-circle').should('be.visible');
        cy.contains('Code has been added.');
        cy.get('.cart-item-promotion .cart-item-label').contains('Thunder Tuesday');
    });

    it('@marketing: use invalid code', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/promotion',
            method: 'post'
        }).as('saveData');
        cy.route({
            url: '/api/v1/search/promotion/**/discounts',
            method: 'post'
        }).as('saveDiscount');

        // Active code in promotion
        cy.contains(`${page.elements.dataGridRow}--0 a`, 'Thunder Tuesday').click();
        cy.get('#sw-field--promotion-active').should('be.visible');
        cy.get('#sw-field--promotion-active').click();
        cy.get('.sw-promotion-sales-channel-select').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-sales-channel-select .sw-select-selection-list__input')
            .type('{esc}');
        cy.get('#sw-field--promotion-useCodes').click();
        cy.get('#sw-field--promotion-code').should('be.enabled');
        cy.get('#sw-field--promotion-code').type('funicular');

        // Add discount
        cy.get('a[title="Discounts"]').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-button--ghost').should('be.visible');
        cy.contains('.sw-button--ghost', 'Add discount').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.wait('@filteredResultCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .clear()
            .type('54');


        // Save final promotion
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@saveDiscount').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Verify Promotion in Storefront
        cy.visit('/');
        cy.get('.product-box').should('be.visible');
        cy.get('.btn-buy').click();
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('#addPromotionOffcanvasCartInput').type('not_funicular');
        cy.get('#addPromotionOffcanvasCart').click();
        cy.contains('Promotion code has been stored but hasn\'t been applied');
        cy.get('.cart-item-promotion').should('not.exist');
    });
});
