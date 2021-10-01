// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Product: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-active');
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{
                        name: 'Red'
                    }]
                });
            })
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@visual: check appearance of basic product workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-product-list-grid').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/product/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-product'
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-product-list__content').should('be.visible');

        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Product] Listing');

        cy.get('.sw-data-grid__row--0 .sw-context-button').click();
        cy.get('.sw-context-menu__content').should('be.visible');

        cy.takeSnapshot('[Product] Listing, context menu open');

        cy.get('.sw-entity-listing__context-menu-edit-action').click();

        // Edit base data of product
        cy.get('.sw-select-product__select_manufacturer')
            .typeSingleSelectAndCheck('shopware AG', '.sw-select-product__select_manufacturer');
        cy.takeSnapshot('[Product] Detail, base', '.sw-product-detail-base');
    });

    it('@visual: check appearance of basic product pricing', () => {
        const page = new ProductPageObject();

        cy.get('.sw-product-list-grid').should('be.visible');
        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-advanced-prices').should('be.visible');
        cy.get('.sw-product-detail__tab-advanced-prices').click();

        cy.get('.sw-product-detail-context-prices__empty-state-card').should('be.visible');
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.contains('.sw-select-result', 'All customers').click();
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('#rule').contains('All customers');

        cy.takeSnapshot('[Product] Detail, advanced prices', '.sw-product-detail-context-prices');
    });

    it('@catalogue @percy: check product property appearance', () => {
        const page = new ProductPageObject();

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-specifications').should('be.visible');
        cy.get('.sw-product-detail__tab-specifications').click();

        cy.get('.sw-product-properties').should('be.visible');
    });

    it('@visual: check appearance of product variant workflow', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST'
        }).as('getPropertyGroups');

        cy.get('.sw-product-list-grid').should('be.visible');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();

        // Take snapshot for visual testing
        cy.wait('@getPropertyGroups').its('response.statusCode').should('equal', 200);

        cy.get('.sw-modal').should('be.visible');

        cy.handleModalSnapshot('Generate variants');
        cy.contains('.group_grid__column-name', 'Color').should('be.visible');
        cy.takeSnapshot('[Product] Detail, Variant generation', '.sw-product-modal-variant-generation');

        // Create and verify one-dimensional variant
        page.generateVariants('Color', [0], 1);

        // Take snapshot for visual testing
        cy.get('.sw-modal').should('not.exist');
        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-product-variants-media-upload').should('be.visible');
        cy.takeSnapshot('[Product] Variants in admin', '.sw-product-variants-overview');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product name')
            .click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Product] Storefront, Variants', '.product-detail-name');
    });

    it('@visual: check appearance of product cross selling workflow', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/product-stream`,
            method: 'POST'
        }).as('saveStream');

        cy.createProductFixture({
            name: 'Original product',
            productNumber: 'RS-11111',
            description: 'Pudding wafer apple pie fruitcake cupcake.'
        }).then(() => {
            cy.createProductFixture({
                name: 'Second product',
                productNumber: 'RS-22222',
                description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping.'
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Third product',
                productNumber: 'RS-33333',
                description: 'Cookie bonbon tootsie roll lemon drops soufflé powder gummies bonbon.'
            });
        });

        // Open product and add cross selling


        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-product-list-grid').should('be.visible');

        cy.contains('Original product').click();

        cy.get('.sw-product-detail__tab-cross-selling').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.contains(
            `.sw-empty-state ${page.elements.ghostButton}`,
            'Add new Cross Selling'
        ).should('be.visible').click();
        cy.get('.product-detail-cross-selling-form').should('be.visible');

        // Fill in cross selling form
        cy.get('#sw-field--crossSelling-name').typeAndCheck('Kunden kauften auch');
        cy.get('#sw-field--crossSelling-product-group')
            .typeSingleSelectAndCheck(
                '2nd Product stream',
                '#sw-field--crossSelling-product-group'
            );
        cy.get('input[name="sw-field--crossSelling-active"]').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Product] Detail, Cross Selling in Admin', '.product-detail-cross-selling-form');

        // Save and verify cross selling stream
        cy.get('.sw-button-process').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');
        cy.contains('Original product').click();
        cy.get('.product-detail-content').should('be.visible');
        cy.get('.product-detail-name').contains('Original product');

        cy.get('.product-cross-selling-tab-navigation')
            .scrollIntoView()
            .should('be.visible');
        cy.get('.product-detail-tab-navigation-link.active').contains('Kunden kauften auch');
        cy.get('.product-slider-item .product-name[title="Second product"]')
            .should('be.visible');
        cy.get('.product-slider-item .product-name[title="Third product"]')
            .should('be.visible');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Product] Storefront, Cross Selling', '.product-slider-item');
    });
});
