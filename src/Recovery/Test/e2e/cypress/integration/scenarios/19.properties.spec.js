/// <reference types="Cypress" />
import PropertyPageObject from '../../support/pages/module/sw-property.page-object';

describe('Create a new property and select value display type', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.createProductFixture({
                name: 'Test Product',
                productNumber: 'TEST-1234',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    linked: true,
                    gross: 64
                }]
            });
        }).then(() => {
            return cy.createPropertyFixture({
                options: [{name: 'Red'}, {name: 'Yellow'}, {name: 'Green'}]
            });
        }).then(() => {
            return cy.createPropertyFixture({
                name: 'Size',
                options: [{name: 'S'}, {name: 'M'}, {name: 'L'}]
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/property/index`);
        });
    });

    it('@package: create new property', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST'
        }).as('searchPropertyGroup');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST'
        }).as('saveProduct');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST'
        }).as('searchSalesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST'
        }).as('searchCategoryDetail');

        const page = new PropertyPageObject();
        const propertyValue = '.sw-product-add-properties-modal__property-values';

        cy.get('h2').should('include.text', 'Attributen');

        // display in product filters deactivate
        cy.contains('Color').click();
        cy.contains('Toon in productfilter').click();
        cy.get(page.elements.propertySaveAction).click();
        cy.wait('@searchPropertyGroup').its('response.statusCode').should('equal', 200);

        // define the new created category to the product
        cy.visit(`${Cypress.env('admin')}#/sw/product/detail`);
        cy.get('[placeholder="Wijs categorieën toe ..."]').should('be.visible')
            .scrollIntoView()
            .type('Home {enter}');
        cy.get('.sw-button-process.sw-product-detail__save-action').click();

        // add product to sales channel
        cy.contains('E2E install test').click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('td.sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 204);
        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@searchSalesChannel').its('response.statusCode').should('equal', 200);

        // define the product under the home category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.url().should('include', 'category/index');
        cy.get('.tree-link > .sw-tree-item__label').click();
        cy.get('[title="Producten"]').click();
        cy.url().should('include', 'products');
        cy.get('.sw-select__selection > input').click()
            .type('Test Product {enter}');
        cy.get('.sw-button-process').click();
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@searchCategoryDetail').its('response.statusCode').should('equal', 200);

        // configure properties under product/specifications
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.url().should('include', 'product/index');

        cy.get('.sw-data-grid__table a').click();
        cy.get('.sw-product-variant-info__product-name').should('be.visible');
        cy.contains('.sw-product-detail-page__tabs .sw-tabs-item', 'specificaties').click();
        cy.url().should('include', 'specifications');

        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('#modalTitleEl').should('be.visible');

        cy.contains('Color').click();
        cy.get(`${propertyValue} .sw-grid__row--0 input`).click();
        cy.get(`${propertyValue} .sw-grid__row--1 input`).click();
        cy.get(`${propertyValue} .sw-grid__row--2 input`).click();
        cy.get('.sw-grid-row.sw-grid__row--0').should('include.text', '3');

        cy.contains('Size').click();
        cy.get('.sw-product-add-properties-modal__property-values .sw-grid__cell-content').should('be.visible');
        cy.get(`${propertyValue} .sw-grid__row--0 input`).click();
        cy.get(`${propertyValue} .sw-grid__row--1 input`).click();
        cy.get(`${propertyValue} .sw-grid__row--2 input`).click();
        cy.get('.sw-grid-row.sw-grid__row--1').should('include.text', '3');

        cy.get('.sw-product-add-properties-modal__button-save').click();
        cy.get('.sw-button-process.sw-product-detail__save-action').click();
        cy.url().should('include', 'product/detail');

        // check from the storefront
        cy.visit('/');
        cy.contains('Home');
        cy.contains('Color').should('not.exist');

        // display on product detail page deactivate, filter activate
        cy.visit(`${Cypress.env('admin')}#/sw/property/index`);
        cy.contains('Color').click();
        cy.get('.sw-property-detail__visible-on-detail').click();
        cy.contains('Toon in productfilter').click();
        cy.get(page.elements.propertySaveAction).click();
        cy.wait('@searchPropertyGroup').its('response.statusCode').should('equal', 200);

        // check from the storefront
        cy.visit('/');
        cy.contains('Home');
        cy.contains('Size').should('exist');
        cy.contains('Color').should('exist');

        // check product details
        cy.get('.product-info a').click();
        cy.contains('Size').should('exist');
        cy.contains('Color').should('not.exist');
    });
});
