// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Product: Visual tests', () => {
    before(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-active');
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }]
                });
            });
    });

    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@visual: check appearance of basic product workflow', () => {
        const page = new ProductPageObject();

        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('Product listing');

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-select-product__select_manufacturer')
            .typeSingleSelectAndCheck('shopware AG', '.sw-select-product__select_manufacturer');
        cy.takeSnapshot('Product detail base', '.sw-product-detail-base');
    });

    it('@visual: check appearance of basic product pricing', () => {
        const page = new ProductPageObject();

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

        cy.takeSnapshot('Product detail - Advanced prices', '.sw-product-detail-context-prices');
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

        cy.get('#sw-field--searchTerm').should('be.visible');
        cy.get('#sw-field--searchTerm').click();
        cy.get('.sw-property-search__tree-selection').should('be.visible');
        cy.contains('.sw-grid__cell-content', 'Color').click();
        cy.contains('.sw-grid__row--0 .sw-grid__cell-content', 'Red').should('be.visible');
        cy.get('.sw-grid__row--0 input').click();

        cy.takeSnapshot('Product detail - Properties', '.sw-property-assignment__label-content');
    });

    it('@visual: check appearance of product variant workflow', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

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
        cy.takeSnapshot('Product - Variant generation', '.sw-product-modal-variant-generation');

        // Create and verify one-dimensional variant
        page.generateVariants('Color', [0], 1);

        // Take snapshot for visual testing
        cy.takeSnapshot('Product - Variants in admin', '.sw-product-variants-overview');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product name')
            .click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Product - Variants in Storefront', '.product-detail-name');
    });

    it('@visual: check appearance of product cross selling workflow', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-stream`,
            method: 'post'
        }).as('saveStream');

        cy.createProductFixture({
            name: 'Original product',
            productNumber: 'RS-11111',
            description: 'Pudding wafer apple pie fruitcake cupcake. Biscuit cotton candy gingerbread liquorice tootsie roll caramels soufflé. Wafer gummies chocolate cake soufflé.'
        }).then(() => {
            cy.createProductFixture({
                name: 'Second product',
                productNumber: 'RS-22222',
                description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping. Cotton candy jelly beans tootsie roll pie tootsie roll chocolate cake brownie. I love pudding brownie I love.'
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Third product',
                productNumber: 'RS-33333',
                description: 'Cookie bonbon tootsie roll lemon drops soufflé powder gummies bonbon. Jelly-o lemon drops cheesecake. I love carrot cake I love toffee jelly beans I love jelly.'
            });
        });

        // Open product and add cross selling
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.contains('Original product').click();

        cy.get('.sw-product-detail__tab-cross-selling').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.contains(
            `.sw-product-detail-cross-selling__empty-state ${page.elements.ghostButton}`,
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
        cy.takeSnapshot('Product - Cross Selling in Admin', '.product-detail-cross-selling-form');

        // Save and verify cross selling stream
        cy.get('.sw-button-process').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

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
        cy.takeSnapshot('Product - Cross Selling in Storefront', '.product-slider-item');
    });
});
