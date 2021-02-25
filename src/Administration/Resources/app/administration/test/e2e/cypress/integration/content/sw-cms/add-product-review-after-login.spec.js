// / <reference types="Cypress" />

import AccountPageObject from '../../../support/pages/account.page-object';

describe('CMS: Check usage and editing of product description reviews element', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
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
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@content: can login to write review when assign category for layout', () => {
        const page = new AccountPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveCategory');

        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
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

        // Replace text element with description & review element
        cy.get('.sw-cms-slot .sw-cms-slot__element-action').click();
        cy.get('.sw-cms-slot__element-selection').should('be.visible');

        cy.get('.sw-cms-el-preview-product-description-reviews').click();

        // Select a product
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
        cy.get('.sw-cms-el-config-product-description-reviews-rating .sw-entity-single-select')
            .typeSingleSelectAndCheck('Product name', '.sw-cms-el-config-product-description-reviews-rating .sw-entity-single-select');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then(() => {
            cy.get('.sw-cms-detail__back-btn').click();
        });

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.product-description-reviews-tab-navigation')
            .scrollIntoView()
            .should('be.visible');
        cy.get('.review-tab').click();
        cy.get('.product-detail-review-teaser-btn').click();
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();
        cy.get('.review-tab').click();
        cy.get('.product-detail-review-teaser-btn').click();
        cy.get('#reviewTitle').typeAndCheckStorefront('Test review');
        cy.get('#reviewContent').typeAndCheckStorefront('This product is the most good product that I have used');
        cy.get('.btn-review-submit').click();
        cy.get('.product-detail-review-item-title p').first().contains('Test review');
        cy.get('.product-detail-review-item-content').first().contains('This product is the most good product that I have used');
    });

    it('@content: can login to write review when assign product for PDP layout', () => {
        const page = new AccountPageObject();

        cy.server();

        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveProductData');

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell-content a').click();
        cy.get('.sw-product-detail__tab-layout').click();
        cy.get('.sw-product-layout-assignment__button').first().click();
        cy.get('.sw-cms-layout-modal__content-item--0').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.get('.sw-product-detail__save-action').click();

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.header-search-input')
            .type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();

        // Login to write review
        cy.get('.review-tab').click();
        cy.get('.product-detail-review-teaser-btn').click();
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();
        cy.get('.review-tab').click();
        cy.get('.product-detail-review-teaser-btn').click();
        cy.get('#reviewTitle').typeAndCheckStorefront('Test review');
        cy.get('#reviewContent').typeAndCheckStorefront('This product is the most good product that I have used');
        cy.get('.btn-review-submit').click();
        cy.get('.product-detail-review-item-title p').first().contains('Test review');
        cy.get('.product-detail-review-item-content').first().contains('This product is the most good product that I have used');
    });
});
