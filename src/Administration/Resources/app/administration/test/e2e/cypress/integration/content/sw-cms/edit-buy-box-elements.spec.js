/// <reference types="Cypress" />
import variantProduct from '../../../fixtures/variant-product.js';

describe('CMS: Check usage and editing of buy box elements', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.createProductFixture(variantProduct);
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@base @content: use simple buy box element', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_10078')) {
                cy.log('Skipping test of deactivated feature \'FEATURE_NEXT_10078\' flag');
                return;
            }

            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/cms-page/*`,
                method: 'patch'
            }).as('saveData');

            cy.route({
                url: `${Cypress.env('apiPath')}/category/*`,
                method: 'patch'
            }).as('saveCategory');

            cy.server().route('GET', '/widgets/cms/buybox/**').as('loadData');

            cy.get('.sw-cms-list-item--0').click();
            cy.get('.sw-cms-section__empty-stage').should('be.visible');

            // Add product box block
            cy.get('.sw-cms-section__empty-stage').click();
            cy.get('#sw-field--currentBlockCategory').select('Text');
            cy.get('.sw-cms-preview-text').should('be.visible');
            cy.get('.sw-cms-preview-text').dragTo('.sw-cms-section__empty-stage');
            cy.get('.sw-cms-block__config-overlay').invoke('show');
            cy.get('.sw-cms-block__config-overlay').should('be.visible');
            cy.get('.sw-cms-block__config-overlay').click();
            cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
            cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');

            // Replace current element with buy box element
            cy.get('.sw-cms-slot .sw-cms-slot__element-action').first().click();
            cy.get('.sw-cms-slot__element-selection').should('be.visible');
            cy.get('.sw-cms-el-preview-buy-box').click();

            // Configure element product
            cy.get('.sw-cms-slot .sw-cms-slot__settings-action').click();
            cy.get('.sw-cms-slot__config-modal').should('be.visible');
            cy.get('.sw-cms-el-config-buy-box .sw-entity-single-select').type('Variant product');
            cy.get('.sw-product-variant-info__specification').contains('green').click();
            cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();
            cy.get('.sw-cms-el-buy-box__price').first().contains('111,00');

            // Save new page layout
            cy.get('.sw-cms-detail__save-action').click();
            cy.wait('@saveData').then(() => {
                cy.get('.sw-cms-detail__back-btn').click();
            });

            // Assign layout to root category
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-tree-item__element').contains('Home').click();
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
            cy.get('.product-detail-price').contains('111');
            cy.get('.product-detail-ordernumber').contains('TEST.2');
            cy.get('.product-detail-configurator-option-label[title="red"]').click();

            // Wait for reloading product variant
            cy.wait('@loadData').then((response) => {
                expect(response).to.have.property('status', 200);
                cy.get('.product-detail-ordernumber').contains('TEST.1');
            });

            // Off canvas
            cy.get('.btn-buy').click();
            cy.get('.offcanvas').should('be.visible');
            cy.get('.cart-item-price').contains('111');
            cy.get('.cart-item-characteristics').contains('color');
            cy.get('.cart-item-characteristics-option').contains('red');
            cy.get('.cart-item-label[title="Variant product"]').should('be.visible');
        });
    });
});
