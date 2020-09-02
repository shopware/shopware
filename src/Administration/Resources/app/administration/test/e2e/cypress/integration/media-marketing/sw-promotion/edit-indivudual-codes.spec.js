/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Promotion: Test promotion with individual codes', () => {
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
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/index`);
            });
    });

    it('@marketing: use individual promotion codes', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/promotion/**/discounts`,
            method: 'post'
        }).as('saveDiscount');

        // Active code in promotion
        cy.contains(`${page.elements.dataGridRow}--0 a`, 'Thunder Tuesday').click();
        cy.get('input[name="sw-field--promotion-active"]').should('be.visible');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-sales-channel-select').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-sales-channel-select .sw-select-selection-list__input')
            .type('{esc}');
        cy.get('input[name="sw-field--promotion-useCodes"]').click();
        cy.get('input[name="sw-field--promotion-useIndividualCodes"]').click();

        // Set individual code
        cy.get('.sw-promotion-code-form__link-manage-individual').should('be.visible');
        cy.get('.sw-promotion-code-form__link-manage-individual').click();

        cy.get('.sw-promotion-code-form__modal-individual').should('be.visible');
        cy.get('#sw-field--promotion-individualCodePattern').typeAndCheck('code-%d');
        cy.get('.sw-promotion-individualcodes__top-bar > .sw-button')
            .click();

        cy.wait('@filteredResultCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-promotion-individualcodes__progress-bar .sw-label__caption').contains('10 / 10');
            cy.awaitAndCheckNotification('Generated 10 new codes.');
        });

        cy.get('.sw-modal__close').click();
        cy.get('.sw-modal').should('not.exist');

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
        cy.get('#addPromotionOffcanvasCartInput').type('code-0');
        cy.get('#addPromotionOffcanvasCart').click();
        cy.get('.alert-success .icon-checkmark-circle').should('be.visible');
        cy.contains('Gift code added successfully.');
        cy.get('.cart-item-promotion .cart-item-label').contains('Thunder Tuesday');
    });
});
