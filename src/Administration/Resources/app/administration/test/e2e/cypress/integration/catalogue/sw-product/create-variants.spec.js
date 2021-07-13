// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import PropertyPageObject from '../../../support/pages/module/sw-property.page-object';

describe('Product: Test variants', () => {
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
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }]
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: add variant to product', () => {
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
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify one-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Yellow');
        cy.get('.sw-data-grid__body').contains('Green');
        cy.get('.sw-data-grid__body').contains('.1');
        cy.get('.sw-data-grid__body').contains('.2');
        cy.get('.sw-data-grid__body').contains('.3');

        // Edit one variant and verify it can be saved save
        cy.get('.sw-data-grid__body').contains('Red').click();
        cy.get('.product-basic-form .sw-inheritance-switch').eq(0).click();
        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('New Product name');
        cy.get(page.elements.productSaveAction).click();
        // Verify updated product
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product name')
            .click();
        cy.get('.product-detail-name').contains('Product name');
        cy.get('.product-detail-configurator-option-label[title="Red"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Yellow"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Green"]')
            .should('be.visible');
    });

    it('@base @catalogue: variants display corresponding name based on specific language', () => {
        const page = new PropertyPageObject();

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('searchUserConfig');

        cy.visit(`${Cypress.env('admin')}#/sw/property/index`);

        // Add option to property group
        cy.wait('@searchUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.clickContextMenuItem(
                '.sw-property-list__edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );
        });

        cy.get(page.elements.cardTitle).contains('Basic information');

        // Switch language to Deutsch
        cy.get('.sw-language-switch__select .sw-entity-single-select__selection-text').contains('English');
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        // poor assertion to check if there is more than 1 language
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length.greaterThan', 1);
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .contains('Deutsch').click();

        // Edit and update property option's name for Deutsch
        cy.get('.sw-property-option-list').scrollIntoView();

        const yellowOption = cy.get('.sw-property-option-list').contains('Yellow').parents('tr');
        yellowOption.dblclick();
        yellowOption.get('#sw-field--item-name').typeAndCheck('Gelb');
        yellowOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        const redOption = cy.get('.sw-property-option-list').contains('Red').parents('tr');
        redOption.dblclick();
        redOption.get('#sw-field--item-name').typeAndCheck('Rot');
        redOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        const greenOption = cy.get('.sw-property-option-list').contains('Green').parents('tr');
        greenOption.dblclick();
        greenOption.get('#sw-field--item-name').typeAndCheck('Grün');
        greenOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        const productPage = new ProductPageObject();

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productPage.elements.contextMenuButton,
            `${productPage.elements.dataGridRow}--0`
        );
        cy.get('.sw-product-detail__tab-variants').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${productPage.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create variant
        productPage.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Gelb');
        cy.get('.sw-data-grid__body').contains('Rot');
        cy.get('.sw-data-grid__body').contains('Grün');

        // Switch to English
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').contains('English');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-data-grid__body').contains('Yellow');
        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Green');

        cy.reload();

        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Yellow');
        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Green');
    });
});
