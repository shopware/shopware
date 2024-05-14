// / <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject    from '../../support/pages/module/sw-product.page-object';

describe('Create a new property, select value display type and test their appearance in the storefront by creating new variants', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.createProductFixture({
                    name: 'Variant Product',
                    productNumber: 'Variant-1234',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            linked: true,
                            gross: 60,
                        },
                    ],
                });
            }).then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }],
                });
            }).then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }],
                });
            }).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@package: add property and multidimensional variant to product', { tags: ['pa-inventory'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('salesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('saveProduct');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('saveCategoryDetail');

        const page = new ProductPageObject();

        // Add product to sales channel
        cy.contains(Cypress.env('storefrontName')).click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 204);
        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@salesChannel').its('response.statusCode').should('equal', 200);

        // Navigate to variant generator listing and start
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`);

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-detail-variants__generated-variants-empty-state-button').click();

        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Size', [0, 1, 2], 3);
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        cy.get('.sw-product-variants__generate-action').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Color', [0, 1, 2], 9);
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
        cy.get('.sw-product-variants-overview').should('be.visible');

        // Verify variant properties in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Variant Product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Variant Product')
            .click();
        cy.contains('.product-detail-name', 'Variant Product');
        cy.get('.product-detail-configurator-option-label[title="Yellow"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Red"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Green"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="S"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="M"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="L"]')
            .should('be.visible');
    });
});
