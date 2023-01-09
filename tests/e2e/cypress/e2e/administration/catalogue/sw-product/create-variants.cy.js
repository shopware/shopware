// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';
import PropertyPageObject from '../../../../support/pages/module/sw-property.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.createPropertyFixture({
            options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }],
        })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }],
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    // NEXT-20024
    it('@base @catalogue: add variant to product', { tags: ['quarantined', 'pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants-empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify one-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.contains('.sw-data-grid__body', 'Red');
        cy.contains('.sw-data-grid__body', 'Yellow');
        cy.contains('.sw-data-grid__body', 'Green');
        cy.contains('.sw-data-grid__body', '.1');
        cy.contains('.sw-data-grid__body', '.2');
        cy.contains('.sw-data-grid__body', '.3');

        // Edit one variant and verify it can be saved save
        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton.sw-skeleton__detail').should('not.exist');
        cy.get('.sw-skeleton.sw-skeleton__listing').should('not.exist');
        cy.get('.sw-data-grid__row--1 a').should('exist');
        cy.contains('.sw-data-grid__row--1 a', 'Red');
        cy.get('.sw-data-grid__row--1 a').click();
        cy.get('.product-basic-form .sw-inheritance-switch').eq(0).click();
        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('New Product name');
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@productCall')
            .its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Product name')
            .click();
        cy.contains('.product-detail-name', 'Product name');
        cy.get('.product-detail-configurator-option-label[title="Red"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Yellow"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Green"]')
            .should('be.visible');
    });

    it('@base @catalogue: variants display corresponding name based on specific language', { tags: ['pa-inventory'] }, () => {
        const page = new PropertyPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('searchUserConfig');

        cy.visit(`${Cypress.env('admin')}#/sw/property/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Add option to property group
        cy.wait('@searchUserConfig')
            .its('response.statusCode').should('equal', 200);
        cy.clickContextMenuItem(
            '.sw-property-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.contains(page.elements.cardTitle, 'Basic information');

        // Switch language to Deutsch
        cy.contains('.sw-language-switch__select .sw-entity-single-select__selection-text', 'English');
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        // poor assertion to check if there is more than 1 language
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length.greaterThan', 1);
        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Deutsch').click();

        // Edit and update property option's name for Deutsch
        cy.get('.sw-property-option-list').scrollIntoView();

        cy.get('.sw-property-option-list').contains('Yellow').parents('tr').dblclick();
        cy.get('#sw-field--item-name').typeAndCheck('Gelb');
        cy.get('.sw-button.sw-data-grid__inline-edit-save').click();

        cy.get('.sw-property-option-list').contains('Red').parents('tr').dblclick();
        cy.get('#sw-field--item-name').typeAndCheck('Rot');
        cy.get('.sw-button.sw-data-grid__inline-edit-save').click();

        cy.get('.sw-property-option-list').contains('Green').parents('tr').dblclick();
        cy.get('#sw-field--item-name').typeAndCheck('Grün');
        cy.get('.sw-button.sw-data-grid__inline-edit-save').click();

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        const productPage = new ProductPageObject();

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productPage.elements.contextMenuButton,
            `${productPage.elements.dataGridRow}--0`,
        );
        cy.get('.sw-product-detail__tab-variants').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants-empty-state ${productPage.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create variant
        productPage.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.contains('.sw-data-grid__body', 'Gelb');
        cy.contains('.sw-data-grid__body', 'Rot');
        cy.contains('.sw-data-grid__body', 'Grün');

        // Switch to English
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.contains('.sw-select-result-list__item-list .sw-select-option--1', 'English');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.contains('.sw-data-grid__body', 'Yellow');
        cy.contains('.sw-data-grid__body', 'Red');
        cy.contains('.sw-data-grid__body', 'Green');

        cy.reload();

        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.contains('.sw-data-grid__body', 'Yellow');
        cy.contains('.sw-data-grid__body', 'Red');
        cy.contains('.sw-data-grid__body', 'Green');
    });
});
