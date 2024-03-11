// / <reference types="Cypress" />
/**
 * @package inventory
 */

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test ACL privileges', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        });
    });

    it('@base @catalogue: can view product', { tags: ['pa-inventory'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST',
        }).as('propertyGroupSearch');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open product
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name div > a`, 'Product name')
            .click();

        // check product values
        cy.get('#sw-field--product-name').scrollIntoView()
            .should('have.value', 'Product name');
        cy.get('#sw-price-field-gross').scrollIntoView()
            .should('have.value', 49.98);

        // check tabs
        cy.get('.sw-product-detail__tab-advanced-prices')
            .scrollIntoView()
            .click();
        cy.get('.sw-product-detail-context-prices__empty-state')
            .should('be.visible');

        cy.get('.sw-product-detail__tab-specifications')
            .scrollIntoView()
            .click();

        cy.get('.sw-product-properties').should('be.visible');

        cy.get('.sw-product-detail__tab-variants')
            .scrollIntoView()
            .click();

        cy.wait('@propertyGroupSearch');

        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.get('.sw-product-detail-variants__generated-variants-empty-state')
            .should('be.visible');

        cy.get('.sw-product-detail__tab-cross-selling')
            .scrollIntoView()
            .click();
        cy.get('.sw-empty-state')
            .should('be.visible');
    });

    it('@base @catalogue: can edit product', { tags: ['pa-inventory'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
            {
                key: 'product',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open product
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name div > a`, 'Product name')
            .click();

        // change name
        cy.get('#sw-field--product-name').scrollIntoView()
            .clear()
            .type('T-Shirt');

        // save product
        cy.get('.sw-product-detail__save-button-group').click();

        // Verify updated product
        cy.wait('@saveProduct')
            .its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'T-Shirt');
    });

    it('@base @catalogue: can create product', { tags: ['pa-inventory'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');
        cy.intercept({
            method: 'POST',
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
        }).as('calculatePrice');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
            {
                key: 'product',
                role: 'editor',
            },
            {
                key: 'product',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.smart-bar__header h2').should('be.visible');
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        // create new product
        cy.get('a[href="#/sw/product/create?creationStates=is-physical"]').click();
        cy.contains('.smart-bar__header h2', 'New product');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('#sw-field--product-name').typeAndCheck('Product with file upload image');
        cy.get('.sw-select-product__select_manufacturer')
            .typeSingleSelectAndCheck('shopware AG', '.sw-select-product__select_manufacturer');
        cy.get('#sw-field--product-taxId').select('Standard rate');
        cy.get('.sw-list-price-field .sw-price-field__gross input').eq(0).type('10').blur();

        // Check net price calculation
        cy.wait('@calculatePrice').then(() => {
            cy.get('.sw-list-price-field .sw-price-field__net input').eq(0).should('have.value', '8.4033613445378');
        });

        cy.get('input[name=sw-field--product-stock]').type('100');

        // Set product visible
        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-product-detail__select-visibility .sw-select-selection-list__input')
            .type('{esc}');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct')
            .its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Product with file upload image');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product with file upload image');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Product with file upload image')
            .click();
        cy.contains('.product-detail-name', 'Product with file upload image');
        cy.contains('.product-detail-price', '10.00');
    });

    it('@base @catalogue: can delete product', { tags: ['pa-inventory'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'delete',
        }).as('deleteData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
            {
                key: 'product',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains(`${page.elements.modal} .sw-listing__confirm-delete-text`,
            'Are you sure you want to delete this item?',
        );
        cy.get(`${page.elements.modal}__footer .sw-button--danger`).click();

        // Verify updated product
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
