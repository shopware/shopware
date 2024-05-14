/// <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import ManufacturerPageObject from '../../support/pages/module/sw-manufacturer.page-object';

describe('Manufacturers: Appearance in Storefront & Product Filter', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Test Product',
            productNumber: 'TEST-3096',
            price: [{
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                linked: true,
                gross: 10.99,
            }],
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/manufacturer/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: create a manufacturer and verify appearance from the storefront', { tags: ['pa-inventory', 'quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('setProductVisibility');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('assignProductToSalesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('assignProductToCategory');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('getProductDetail');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-manufacturer`,
            method: 'POST',
        }).as('saveManufacturer');

        const page = new ProductPageObject();
        const manufacturerPage = new ManufacturerPageObject();
        const salesChannel = Cypress.env('storefrontName');
        const manufacturerName = 'Test AG';

        // create new manufacturer
        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Fabrikant');
        cy.get(page.elements.primaryButton).contains('Fabrikant aanmaken').click();
        cy.url().should('contain', '#/sw/manufacturer/create');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input[name=name]').clearTypeAndCheck(manufacturerName);
        cy.get('input[name=link]').clearTypeAndCheck('https://google.com/doodles');
        cy.get(manufacturerPage.elements.manufacturerSave).click();

        cy.wait('@saveManufacturer').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(manufacturerPage.elements.smartBarBack).click();
        cy.url().should('contain', '#/sw/manufacturer/index');
        cy.contains(manufacturerName);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // assign manufacturer to the product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.contains('.sw-page__smart-bar-amount', '1');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.url().should('include', 'product/detail');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('#manufacturerId').typeSingleSelectAndCheck(manufacturerName, '#manufacturerId');
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@getProductDetail').its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // add product to sales channel
        cy.contains(salesChannel).click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@setProductVisibility').its('response.statusCode').should('equal', 204);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@assignProductToSalesChannel').its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Define the product under the home category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'category/index');
        cy.get('.tree-link > .sw-tree-item__label').click();
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.url().should('include', 'products');
        cy.get('.sw-select__selection > input').click()
            .type('Test Product {enter}');
        cy.get('.sw-button-process').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@assignProductToCategory').its('response.statusCode').should('equal', 200);

        // check from the storefront
        cy.visit('/');
        cy.contains('Home');
        cy.contains('Manufacturer').click();
        cy.get('.form-check-label.filter-multi-select-item-label').should('be.visible')
            .and('include.text', manufacturerName);

        // check product details
        cy.get('.product-info a').click();
        cy.contains(manufacturerName).should('exist');
    });

});
