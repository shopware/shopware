/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Visual tests', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @catalogue: create and read product', { tags: ['pa-services-settings'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post',
        }).as('saveData');
        cy.intercept({
            url: '/api/_action/calculate-price',
            method: 'post',
        }).as('calculatePrice');

        // Add basic data to product
        cy.get('a[href="#/sw/product/create?creationStates=is-physical"]').click();

        cy.get('input[name=sw-field--product-name]').typeAndCheck('Product with file upload image');

        // Check net price calculation
        const rate = Cypress.env('locale') === 'en-GB' ? 'Standard rate' : 'Standard-Satz';
        cy.get('select[name=sw-field--product-taxId]').select(rate);
        cy.get('.sw-list-price-field > :nth-child(1) #sw-price-field-gross').type('10');
        cy.get('.sw-list-price-field > :nth-child(1) #sw-price-field-gross').blur();

        cy.wait('@calculatePrice').its('response.statusCode').should('equal', 200);
        cy.get('.sw-list-price-field > :nth-child(1) #sw-price-field-net').should('have.value', '8.4033613445378');

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
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-loader').should('not.exist');
        const save = Cypress.env('locale') === 'en-GB' ? 'Save' : 'Speichern';
        cy.get(page.elements.productSaveAction).contains(save).trigger('mouseout').trigger('mouseleave');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Product detail base`, '.sw-product-detail-base', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Product with file upload image');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Product with file upload image');

        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Product listing`, null, null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
