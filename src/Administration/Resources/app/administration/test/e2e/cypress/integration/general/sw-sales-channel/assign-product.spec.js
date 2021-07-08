// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';
import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Sales Channel: Test product assignment operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.searchViaAdminApi({
                    endpoint: 'category',
                    data: {
                        field: 'name',
                        value: 'Home'
                    }
                });
            })
            .then(({ id: categoryId }) => {
                cy.createCategoryFixture({
                    name: 'Test category',
                    type: 'page',
                    parentId: categoryId,
                    active: true
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@general: assign individual products to sales channel', () => {
        cy.onlyOnFeature('FEATURE_NEXT_12437');
        const salesChannelPage = new SalesChannelPageObject();
        const productPage = new ProductPageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/product`,
            method: 'post'
        }).as('saveProduct');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'post'
        }).as('calculatePrice');

        cy.route({
            url: `${Cypress.env('apiPath')}/product-visibility`,
            method: 'post'
        }).as('saveProductVisibility');


        // Add basic data to product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('a[href="#/sw/product/create"]').click();

        cy.get('input[name=sw-field--product-name]').typeAndCheck('First Product');

        cy.get('select[name=sw-field--product-taxId]').select('Standard rate');
        cy.get('.sw-list-price-field .sw-price-field__gross input').eq(0).type('10').type('{enter}');
        cy.wait('@calculatePrice').then(() => {
            cy.get('.sw-list-price-field .sw-price-field__net input').eq(0).should('have.value', '8.4033613445378');
        });

        cy.get('input[name=sw-field--product-stock]').type('100');

        cy.get('.sw-product-detail__select-visibility').scrollIntoView();

        // Assign root category in tree field
        cy.get('.sw-category-tree__input-field').focus().type('Home');
        // wait for the result list to update
        cy.get('.sw-category-tree-field__results').contains('Home');
        cy.get('.sw-category-tree__input-field').focus().type('{enter}');

        cy.get(productPage.elements.productSaveAction).click();
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Go to sales channel Storefront
        salesChannelPage.openSalesChannel('Storefront', 1);
        cy.get('.sw-tabs-item').contains('Products').click();
        cy.get('.sw-empty-state__actions').click();

        // Open product assignment modal
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('First Product');
        cy.get('.sw-modal__body .sw-data-grid__row--0 .sw-field--checkbox').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();

        cy.wait('@saveProductVisibility').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-modal__body').should('not.exist');
        });

        cy.get('.sw-empty-state').should('not.exist');
        cy.get('.sw-data-grid').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('First Product');

        // Go to storefront for checking
        cy.visit('/');

        cy.get('input[name=search]').type('First Product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('First Product')
            .click();

        cy.get('.product-detail-name').contains('First Product');
        cy.get('.product-detail-price').contains('10.00');
    });

    it('@general: assign product from category to sales channel', () => {
        cy.onlyOnFeature('FEATURE_NEXT_12437');
        const salesChannelPage = new SalesChannelPageObject();
        const productPage = new ProductPageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/product`,
            method: 'post'
        }).as('saveProduct');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'post'
        }).as('calculatePrice');

        cy.route({
            url: `${Cypress.env('apiPath')}/product-visibility`,
            method: 'post'
        }).as('saveProductVisibility');


        // Add basic data to product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('a[href="#/sw/product/create"]').click();

        cy.get('input[name=sw-field--product-name]').typeAndCheck('First Product');
        cy.get('select[name=sw-field--product-taxId]').select('Standard rate');
        cy.get('.sw-list-price-field .sw-price-field__gross input').eq(0).type('10').type('{enter}');
        cy.wait('@calculatePrice').then(() => {
            cy.get('.sw-list-price-field .sw-price-field__net input').eq(0).should('have.value', '8.4033613445378');
        });

        cy.get('input[name=sw-field--product-stock]').type('100');

        // Assign Test category in tree field
        cy.get('.sw-product-detail__select-visibility').scrollIntoView();
        cy.get('.sw-category-tree__input-field').focus().type('Test category').type('{enter}');

        cy.get(productPage.elements.productSaveAction).click();
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Go to sales channel Storefront
        salesChannelPage.openSalesChannel('Storefront', 1);
        cy.get('.sw-tabs-item').contains('Products').click();
        cy.get('.sw-empty-state__actions').click();

        // Open product assignment modal
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body .sw-tabs-item').contains('Category selection').click();

        // Search Test category
        cy.get('.sw-sales-channel-product-assignment-categories .sw-simple-search-field').type('Test');
        cy.get('.sw-sales-channel-product-assignment-categories__search-results .sw-field__checkbox').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();

        cy.wait('@saveProductVisibility').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-modal__body').should('not.exist');
        });

        cy.get('.sw-empty-state').should('not.exist');
        cy.get('.sw-data-grid').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('First Product');

        // Go to storefront for checking
        cy.visit('/');

        cy.get('input[name=search]').type('First Product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('First Product')
            .click();

        cy.get('.product-detail-name').contains('First Product');
        cy.get('.product-detail-price').contains('10.00');
    });
});
