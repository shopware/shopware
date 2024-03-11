// / <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test download product', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @catalogue: create and read download product', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        // Add basic data to product
        cy.get('.sw-product-list__add-other-context-button').click();
        cy.get('a[href="#/sw/product/create?creationStates=is-download"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input[name=sw-field--product-name]').typeAndCheck('Downloadable product');
        cy.get('.sw-select-product__select_manufacturer')
            .typeSingleSelectAndCheck('shopware AG', '.sw-select-product__select_manufacturer');

        if (Cypress.isBrowser({ family: 'chromium' })) {
            // Add image to product
            cy.get('.sw-product-download-form .sw-media-upload-v2__file-input')
                .attachFile('img/sw-login-background.png');

            cy.get('.sw-product-download-form__row').should('contain', 'sw-login-background.png');
            cy.awaitAndCheckNotification('File has been saved.');
        }

        // Set price
        cy.get('select[name=sw-field--product-taxId]').select('Standard rate');
        cy.get('.sw-list-price-field .sw-price-field__gross input').eq(0).type('10').type('{enter}');

        // Set product visible
        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-product-detail__select-visibility .sw-select-selection-list__input')
            .type('{esc}');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // verify that it is a downloadable product (files still visible)
        cy.get('.sw-product-download-form__row')
            .scrollIntoView()
            .should('be.visible')
            .should('contain', 'sw-login-background.png');

        // verify in product listing
        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`,
            'Downloadable product');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Downloadable product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name',
            'Downloadable product')
            .click();

        cy.contains('.product-detail-name', 'Downloadable product');
        cy.contains('.product-detail-price', '10.00');
        // TODO: verify display of downloadable product in storefront (availability)
    });
});
