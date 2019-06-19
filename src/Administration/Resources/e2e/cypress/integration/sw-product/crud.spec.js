// / <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';

describe('Product: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@p create and read product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/product',
            method: 'post'
        }).as('saveData');

        cy.get('a[href="#/sw/product/create"]').click();
        cy.get('input[name=sw-field--product-name]').type('Product with file upload image');
        cy.get('.sw-select-product__select_manufacturer').typeSingleSelectAndCheck('shopware AG');
        cy.get('select[name=sw-field--product-taxId]').select('19%');
        cy.get('input[name=sw-field--price-gross]').type('10');
        cy.get('input[name=sw-field--price-net]').should('have.value', '8.4');
        cy.get('input[name=sw-field--product-stock]').type('100');
        cy.get(page.elements.productSaveAction).click();

        // Verify new product
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Product with file upload image');
        });
    });

    it('@p update and read product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/product/*',
            method: 'patch'
        }).as('saveData');

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-product-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('input[name=sw-field--product-name]').clear().type('What remains of Edith Finch');
        cy.get('input[name=sw-field--product-active]').click();
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then(() => {
            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('What remains of Edith Finch');
        });
    });

    it('@p delete product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/product/*',
            method: 'delete'
        }).as('deleteData');

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-product-list__confirm-delete-text`).contains(
            'Are you sure you really want to delete the product "Product name"?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.primaryButton}`).click();

        // Verify updated product
        cy.wait('@deleteData').then(() => {
            cy.get(page.elements.emptyState).should('be.visible');
        });
    });
});
