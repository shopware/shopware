// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @catalogue: can view product', () => {
        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            }
        ]);

        cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-product').click();

        // open product
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name div > a`)
            .contains('Product name')
            .click();

        // check product values
        cy.get('#sw-field--product-name').scrollIntoView()
            .should('have.value', 'Product name');
        cy.get('#sw-price-field-gross').scrollIntoView()
            .should('have.value', '64');

        // check tabs
        cy.get('.sw-product-detail__tab-advanced-prices')
            .scrollIntoView()
            .click();
        cy.get('.sw-product-detail-context-prices__empty-state')
            .should('be.visible');

        cy.get('.sw-product-detail__tab-properties')
            .scrollIntoView()
            .click();

        cy.contains('Create properties and property values first, then return here to assign them.');

        cy.get('.sw-product-detail__tab-variants')
            .scrollIntoView()
            .click();
        cy.get('.sw-product-detail-variants__generated-variants__empty-state')
            .should('be.visible');

        cy.get('.sw-product-detail__tab-cross-selling')
            .scrollIntoView()
            .click();
        cy.get('.sw-product-detail-cross-selling__empty-state-inner')
            .should('be.visible');
    });

    it('@base @catalogue: can edit product', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product/*',
            method: 'patch'
        }).as('saveProduct');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            },
            {
                key: 'product',
                role: 'editor'
            }
        ]);

        cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-product').click();

        // open product
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name div > a`)
            .contains('Product name')
            .click();

        // change name
        cy.get('#sw-field--product-name').scrollIntoView()
            .clear()
            .type('T-Shirt');

        // save product
        cy.get('.sw-product-detail__save-button-group').click();

        // Verify updated product
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('T-Shirt');
    });

    it('@base @catalogue: can create product', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product',
            method: 'post'
        }).as('saveProduct');
        cy.route({
            url: '/api/v*/_action/calculate-price',
            method: 'post'
        }).as('calculatePrice');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            },
            {
                key: 'product',
                role: 'editor'
            },
            {
                key: 'product',
                role: 'creator'
            }
        ]);

        cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-product').click();

        // create new product
        cy.get('a[href="#/sw/product/create"]').click();

        cy.get('#sw-field--product-name').typeAndCheck('Product with file upload image');
        cy.get('.sw-select-product__select_manufacturer')
            .typeSingleSelectAndCheck('shopware AG', '.sw-select-product__select_manufacturer');
        cy.get('#sw-field--product-taxId').select('Standard rate');
        cy.get('.sw-list-price-field .sw-price-field-gross').eq(0).type('10');

        // Check net price calculation
        cy.wait('@calculatePrice').then(() => {
            cy.get('.sw-list-price-field .sw-price-field-net input').eq(0).should('have.value', '8.4');
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
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Product with file upload image');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product with file upload image');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product with file upload image')
            .click();
        cy.get('.product-detail-name').contains('Product with file upload image');
        cy.get('.product-detail-price').contains('10.00');
    });

    it('@base @catalogue: can delete product', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product/*',
            method: 'delete'
        }).as('deleteData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            },
            {
                key: 'product',
                role: 'deleter'
            }
        ]);

        cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-product').click();

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-listing__confirm-delete-text`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer .sw-button--danger`).click();

        // Verify updated product
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
