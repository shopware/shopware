/**
 * @package content
 */
// / <reference types="Cypress" />

describe('CMS: Check usage and editing of product description reviews element', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Product name',
                    productNumber: 'RS-11111',
                    description: 'Pudding wafer apple pie fruitcake cupcake'
                });
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@content: use product description reviews element in another block', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'PATCH'
        }).as('saveProductData');

        cy.get('.sw-cms-list-item--0').click();

        // Add text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Text');
        cy.get('.sw-cms-preview-text').should('be.visible');
        cy.get('.sw-cms-preview-text').dragTo('.sw-cms-section__empty-stage');

        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

        // Replace text element with cross selling element
        cy.get('.sw-cms-slot .sw-cms-slot__element-action').click();
        cy.get('.sw-cms-slot__element-selection').should('be.visible');

        cy.get('.sw-cms-el-preview-product-description-reviews + .element-selection__overlay-action-select').first().invoke('show');
        cy.get('.sw-cms-el-preview-product-description-reviews + .element-selection__overlay-action-select').first().should('be.visible');
        cy.get('.sw-cms-el-preview-product-description-reviews + .element-selection__overlay-action-select').first().click();


        // Select a product
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
        cy.get('.sw-cms-el-config-product-description-reviews-rating .sw-entity-single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-cms-el-config-product-description-reviews-rating .sw-entity-single-select');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.contains('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.product-description-reviews-tab-navigation')
            .scrollIntoView()
            .should('be.visible');
        cy.contains('.product-detail-description-title', 'Product name');
        cy.contains('.product-detail-description-text', 'Pudding wafer apple pie fruitcake cupcake');
    });

    it('@content: use product description reviews block in landing page', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-cms-list-item--0').click();

        // Add product description reviews block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Commerce');
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(5)').scrollIntoView();
        cy.get('.sw-cms-block-preview-product-description-reviews').should('be.visible');
        cy.get('.sw-cms-block-preview-product-description-reviews').dragTo('.sw-cms-section__empty-stage');

        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

        // Select a product
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
        cy.get('.sw-cms-el-config-product-description-reviews-rating .sw-entity-single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-cms-el-config-product-description-reviews-rating .sw-entity-single-select');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.contains('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.product-description-reviews-tab-navigation')
            .scrollIntoView()
            .should('be.visible');
        cy.contains('.product-detail-description-title', 'Product name');
        cy.contains('.product-detail-description-text', 'Pudding wafer apple pie fruitcake cupcake');
    });
});
