// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Product: Edit in various ways', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createCategoryFixture({
                    name: 'Sport',
                    active: true
                });
            })
            .then(() => {
                return cy.createCategoryFixture({
                    name: 'Anime',
                    active: true
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: set list price', () => {
        cy.server();
        cy.route({
            url: '/api/search/product',
            method: 'post'
        }).as('saveProduct');

        const page = new ProductPageObject();

        // go to product detail page
        cy.get('.sw-data-grid__cell-content :nth-child(2) a').click();

        // go to variant tab
        cy.get('.sw-product-detail__tab-variants').click();

        // open variant generation modal
        cy.get('.sw-product-detail-variants__generated-variants__empty-state .sw-button').click();

        // generate variants
        page.generateVariants('Color', [0, 1], 2);

        cy.wait('@saveProduct').then(xhr => {
            expect(xhr).to.have.property('status', 200);
        });

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        // scroll to switch and click it
        cy.get('.sw-product-seo-form .sw-field--switch input')
            .scrollIntoView()
            .check();

        cy.get('.sw-product-seo-form .sw-select')
            .typeSingleSelectAndCheck('Green', '.sw-product-seo-form .sw-select');

        cy.wait('@searchCall').then(xhr => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-button-process').click();

        // checking if product got saved. 'product call' alias comes from the product.generateVariants method
        cy.wait('@productCall').then(xhr => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.visit(`${Cypress.config('baseUrl')}/Product-name/RS-333.2`);

        cy.get('link[rel="canonical"]')
            .should('have.attr', 'href', `${Cypress.config('baseUrl')}/Product-name/RS-333.1`);

        cy.visit(`${Cypress.config('baseUrl')}/Product-name/RS-333.1`);

        cy.get('link[rel="canonical"]')
            .should('have.attr', 'href', `${Cypress.config('baseUrl')}/Product-name/RS-333.1`);
    });

    it('@catalogue: check Seo Url Category is inheritance when variant\'s category inherited from parent', () => {
        const page = new ProductPageObject();
        const categoryPage = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('updateProduct');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        categoryPage.resetCategory();
        categoryPage.selectCategory('Sport');
        categoryPage.selectCategory('Home');
        categoryPage.selectCategory('Anime');

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@updateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('.sw-seo-main-category').scrollIntoView();
        cy.get('.sw-seo-main-category')
            .typeSingleSelectAndCheck(
                'Sport',
                '.sw-seo-main-category'
            );

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@updateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify multi-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        // Check field inheritance in variant
        cy.get('.sw-product-variants-overview__single-variation').contains('Red').click();

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('.sw-seo-main-category').scrollIntoView().contains('Sport');

        cy.get('.sw-seo-url__card-seo-additional')
            .find('.sw-inheritance-switch--is-inherited')
            .scrollIntoView()
            .should('be.visible');
    });

    it('@catalogue: check Seo Url Category is not inheritance when variant\'s category inherited from parent', () => {
        const page = new ProductPageObject();
        const categoryPage = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('updateProduct');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        categoryPage.resetCategory();
        categoryPage.selectCategory('Sport');
        categoryPage.selectCategory('Home');
        categoryPage.selectCategory('Anime');

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@updateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('.sw-seo-main-category').scrollIntoView();
        cy.get('.sw-seo-main-category')
            .typeSingleSelectAndCheck(
                'Sport',
                '.sw-seo-main-category'
            );

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@updateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify multi-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        // Check field inheritance in variant
        cy.get('.sw-product-variants-overview__single-variation').contains('Red').click();

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('.sw-seo-main-category').scrollIntoView().contains('Sport');

        cy.get('.sw-seo-url__card-seo-additional')
            .find('.sw-inheritance-switch--is-inherited')
            .scrollIntoView()
            .should('be.visible')
            .click();


        cy.get('.sw-seo-main-category').scrollIntoView();
        cy.get('.sw-seo-main-category')
            .typeSingleSelectAndCheck(
                'Anime',
                '.sw-seo-main-category'
            );

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-seo-main-category').scrollIntoView().contains('Anime');
    });

    it('@catalogue: check Seo Url Category is not inheritance when variant\'s category didn\'t inherit from parent', () => {
        const page = new ProductPageObject();
        const categoryPage = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('updateProduct');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        categoryPage.resetCategory();

        categoryPage.selectCategory('Sport');
        categoryPage.selectCategory('Home');


        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@updateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('.sw-seo-main-category').scrollIntoView();
        cy.get('.sw-seo-main-category')
            .typeSingleSelectAndCheck(
                'Sport',
                '.sw-seo-main-category'
            );

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@updateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify multi-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        // Check field inheritance in variant
        cy.get('.sw-product-variants-overview__single-variation').contains('Green').click();

        cy.get('.sw-product-detail__select-category')
            .scrollIntoView()
            .parent()
            .find('.sw-inheritance-switch--is-inherited')
            .should('be.visible')
            .click();

        categoryPage.clearCategory('Sport');

        categoryPage.selectCategory('Anime');

        // go back to seo tab
        cy.get('.sw-product-detail__tab-seo').click();

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('.sw-seo-url__card-seo-additional .sw-inheritance-switch--is-inherited').should('not.exist');

        cy.get('.sw-seo-url__card-seo-additional')
            .scrollIntoView()
            .typeSingleSelectAndCheck(
                'Anime',
                '.sw-seo-main-category'
            );

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-seo-main-category').scrollIntoView().contains('Anime');
    });
});
