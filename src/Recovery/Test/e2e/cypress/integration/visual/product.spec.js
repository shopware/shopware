/// <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import ProductStreamObject from '../../support/pages/module/sw-product-stream.page-object';

describe('Product: Visual tests', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: create and read product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product',
            method: 'post'
        }).as('saveData');
        cy.route({
            url: '/api/v*/_action/calculate-price',
            method: 'post'
        }).as('calculatePrice');

        // Add basic data to product
        cy.get('a[href="#/sw/product/create"]').click();

        cy.get('input[name=sw-field--product-name]').typeAndCheck('Product with file upload image');

        // Check net price calculation
        cy.get('select[name=sw-field--product-taxId]').select('Standard rate');
        cy.get('#sw-price-field-gross').type('10');
        cy.wait('@calculatePrice').then(() => {
            cy.get('#sw-price-field-net').should('have.value', '8.4');
        });

        cy.get('input[name=sw-field--product-stock]').type('100');

        // Set product visible
        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();

        const saleschannel = Cypress.env('testDataUsage') ? 'Footwear' : 'E2E install test';
        cy.get('.sw-product-detail__select-visibility').typeMultiSelectAndCheck(saleschannel);
        cy.get('.sw-product-detail__select-visibility .sw-select-selection-list__input')
            .type('{esc}');
        cy.get(page.elements.productSaveAction).click();

        // Save product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-loader').should('not.exist');
        const save = Cypress.env('locale') === 'en-GB' ? 'Save' : 'Speichern';
        cy.get(page.elements.productSaveAction).contains(save);
        cy.changeElementStyling(
            '.sw-version__info',
            'visibility: hidden'
        );
        cy.takeSnapshot('Product detail base', '.sw-product-detail-base');

        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Product with file upload image');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Product with file upload image');

        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.changeElementStyling(
            '.sw-version__info',
            'visibility: hidden'
        );
        cy.takeSnapshot('Product listing');
    });
});
