// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Sales Channel: Test product assignment operations', () => {
    beforeEach(() => {
        cy.searchViaAdminApi({
            endpoint: 'category',
            data: {
                field: 'name',
                value: 'Home',
            },
        }).then(({ id: categoryId }) => {
            cy.createCategoryFixture({
                name: 'Test category',
                type: 'page',
                parentId: categoryId,
                active: true,
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@general: assign individual products to sales channel', { tags: ['pa-sales-channels', 'VUE3'] }, () => {
        const salesChannelPage = new SalesChannelPageObject();
        const productPage = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'POST',
        }).as('calculatePrice');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('saveProductVisibility');


        // Add basic data to product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.smart-bar__header h2').should('be.visible');
        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('a[href="#/sw/product/create?creationStates=is-physical"]').click();
        cy.contains('.smart-bar__header h2', 'New product');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

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
        cy.contains('.sw-category-tree-field__results_popover', 'Home');
        cy.get('.sw-category-tree__input-field').focus().type('{enter}');

        cy.get(productPage.elements.productSaveAction).click();
        cy.wait('@saveProduct')
            .its('response.statusCode').should('equal', 200);

        // Go to sales channel Storefront
        salesChannelPage.openSalesChannel('Storefront', 1);
        cy.contains('.sw-tabs-item', 'Products').click();
        cy.get('.sw-empty-state__actions').click();

        // Open product assignment modal
        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body .sw-data-grid__row--0 .sw-data-grid__cell--name', 'First Product');
        cy.get('.sw-modal__body .sw-data-grid__row--0 .sw-field--checkbox').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();

        cy.wait('@saveProductVisibility')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal__body').should('not.exist');

        cy.get('.sw-empty-state').should('not.exist');
        cy.get('.sw-data-grid').should('be.visible');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--name', 'First Product');

        // Go to storefront for checking
        cy.visit('/');

        cy.get('input[name=search]').type('First Product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'First Product')
            .click();

        cy.contains('.product-detail-name', 'First Product');
        cy.contains('.product-detail-price', '10.00');
    });

    it('@general: assign product from category to sales channel', { tags: ['pa-sales-channels'] }, () => {
        const salesChannelPage = new SalesChannelPageObject();
        const productPage = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'POST',
        }).as('calculatePrice');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('saveProductVisibility');


        // Add basic data to product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.smart-bar__header h2').should('be.visible');
        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.get('a[href="#/sw/product/create?creationStates=is-physical"]').click();
        cy.contains('.smart-bar__header h2', 'New product');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

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
        cy.wait('@saveProduct')
            .its('response.statusCode').should('equal', 200);

        // Go to sales channel Storefront
        salesChannelPage.openSalesChannel('Storefront', 1);
        cy.contains('.sw-tabs-item', 'Products').click();
        cy.get('.sw-empty-state__actions').click();

        // Open product assignment modal
        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body .sw-tabs-item', 'Category selection').click();

        // Search Test category
        cy.get('.sw-sales-channel-product-assignment-categories .sw-simple-search-field').type('Test');
        cy.get('.sw-sales-channel-product-assignment-categories__search-results .sw-field__checkbox').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();

        cy.wait('@saveProductVisibility')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal__body').should('not.exist');

        cy.get('.sw-empty-state').should('not.exist');
        cy.get('.sw-data-grid').should('be.visible');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--name', 'First Product');

        // Go to storefront for checking
        cy.visit('/');

        cy.get('input[name=search]').type('First Product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'First Product')
            .click();

        cy.contains('.product-detail-name', 'First Product');
        cy.contains('.product-detail-price', '10.00');
    });
});
