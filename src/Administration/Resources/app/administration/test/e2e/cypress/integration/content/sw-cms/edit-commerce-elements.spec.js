// / <reference types="Cypress" />

describe('CMS: Check usage and editing of commerce elements', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-valid');
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'First product',
                    productNumber: 'RS-11111',
                    description: 'Pudding wafer apple pie fruitcake cupcake. Biscuit cotton candy gingerbread liquorice tootsie roll caramels soufflé. Wafer gummies chocolate cake soufflé.'
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Second product',
                    productNumber: 'RS-22222',
                    description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping. Cotton candy jelly beans tootsie roll pie tootsie roll chocolate cake brownie. I love pudding brownie I love.'
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Third product',
                    productNumber: 'RS-33333',
                    description: 'Cookie bonbon tootsie roll lemon drops soufflé powder gummies bonbon. Jelly-o lemon drops cheesecake. I love carrot cake I love toffee jelly beans I love jelly.'
                });
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@base @content: use simple product block', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add product box block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Commerce');
        cy.get('.sw-cms-preview-product-three-column').should('be.visible');
        cy.get('.sw-cms-preview-product-three-column').dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

        // Configure first product
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');
        cy.get('.sw-cms-el-config-product-box .sw-entity-single-select')
            .typeSingleSelectAndCheck('First product', '.sw-cms-el-config-product-box .sw-entity-single-select');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();
        cy.get('.sw-cms-el-product-box__name').first().contains('First product');

        // Configure second product
        cy.get('.sw-cms-slot:nth-of-type(2) .sw-cms-slot__settings-action').click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');
        cy.get('.sw-cms-el-config-product-box .sw-entity-single-select')
            .typeSingleSelectAndCheck('Second product', '.sw-cms-el-config-product-box .sw-entity-single-select');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

        // Configure third product
        cy.get('.sw-cms-slot:nth-of-type(3) .sw-cms-slot__settings-action').click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');
        cy.get('.sw-cms-el-config-product-box .sw-entity-single-select')
            .typeSingleSelectAndCheck('Third product', '.sw-cms-el-config-product-box .sw-entity-single-select');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();
        cy.get('.sw-cms-slot:nth-of-type(3) .sw-cms-el-product-box__name').contains('Third product');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

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

        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.product-name[title="First product"]').should('be.visible');
        cy.get('.product-name[title="Second product"]').should('be.visible');
        cy.get('.product-name[title="Third product"]').should('be.visible');
    });

    it('@base @content: use product slider block with dynamic product group', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add product slider block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Commerce');
        cy.get('.sw-cms-preview-product-slider').should('be.visible');
        cy.get('.sw-cms-preview-product-slider').dragTo('.sw-cms-section__empty-stage');

        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

        // Configure product slider
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').first().click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');

        // Change the type to dynamic product group
        cy.get('.sw-cms-el-config-product-slider__product-assignment-type-select')
            .typeSingleSelectAndCheck('Dynamic product group', '.sw-cms-el-config-product-slider__product-assignment-type-select');

        cy.get('.sw-cms-el-config-product-slider__product-stream-performance-hint').should('be.visible');

        // Sorting options and preview link should be disabled when to dynamic product group is selected
        cy.get('.sw-cms-el-config-product-slider__product-stream-sorting')
            .should('have.class', 'is--disabled');
        cy.get('.sw-cms-el-config-product-slider__product-stream-limit')
            .should('have.class', 'is--disabled');
        cy.get('.sw-cms-el-config-product-slider__product-stream-preview-link')
            .should('have.class', 'is--disabled');

        // Select dynamic product group
        cy.get('.sw-cms-el-config-product-slider__product-stream-select')
            .typeSingleSelectAndCheck('2nd Product stream', '.sw-cms-el-config-product-slider__product-stream-select');

        // Verify dynamic product group preview is available
        cy.get('.sw-cms-el-config-product-slider__product-stream-preview-link').click();
        cy.get('.sw-cms-el-config-product-slider__product-stream-preview-modal').should('be.visible');
        cy.get('.sw-cms-el-config-product-slider__product-stream-preview-modal .sw-product-stream-grid-preview__grid')
            .should('be.visible');

        // Close preview
        cy.get('.sw-cms-el-config-product-slider__product-stream-preview-modal .sw-modal__footer .sw-button').click();

        // Close config modal
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

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

        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-element-product-slider').should('be.visible');
        cy.get('.product-name[title="First product"]').should('be.visible');
        cy.get('.product-name[title="Second product"]').should('be.visible');
        cy.get('.product-name[title="Third product"]').should('be.visible');
    });
});
