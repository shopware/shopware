// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.createProductVariantFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@catalogue: add variant with surcharge to product', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            method: 'PATCH',
            url: `${Cypress.env('apiPath')}/product/*`
        }).as('saveData');
        cy.intercept({
            method: 'GET',
            url: `${Cypress.config('baseUrl')}/detail/**/switch?options=*`
        }).as('changeVariant');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST'
        }).as('searchVariantGroup');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.contains('.sw-data-grid__body', 'Red');
        cy.contains('.sw-data-grid__body', 'Green');

        // Get green variant
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-simple-search-field--form input').should('be.visible');

        cy.wait('@searchVariantGroup')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-simple-search-field--form input').type('Green');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-data-grid__row--1').should('not.exist');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--name', 'Green');

        // Find variant to set surcharge on
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-price-preview').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({ force: true });
        cy.get('.sw-data-grid__row--0 .sw-price-preview').should('not.exist');

        // Set surcharge
        cy.get('.is--inline-edit .sw-inheritance-switch').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-price-field__lock').first().click();
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().should('be.visible');
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().should('be.enabled');
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().should('have.value', '100');
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().clear().clear();
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().clearTypeAndCheck('333');
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().type('{enter}');

        // Assert surcharge
        cy.get('.sw-data-grid__row--0 #sw-price-field-net')
            .first()
            .invoke('val')
            .should('eq', '167.33668341709');
        cy.get('.icon--regular-link-horizontal-slash').should('be.visible');
        cy.get('.sw-data-grid__inline-edit-save').click();

        // Validate product
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('Product "Green" has been saved.');

        // Find variant in Storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Variant product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Variant product name')
            .click();

        // Verify in storefront
        cy.contains('.product-detail-name', 'Variant product name');
        cy.get('.product-detail-configurator-option-label[title="Red"]')
            .should('be.visible');
        cy.get('.product-detail-configurator-option-label[title="Green"]')
            .should('be.visible');
        cy.get('.product-detail-buy').should('be.visible');

        // Ensure that variant "Green" is checked at the moment the test runs
        cy.get('.product-detail-configurator-option-label[title="Green"]').then(($btn) => {
            const inputId = $btn.attr('for');

            cy.get(`#${inputId}`).then(($input) => {
                if (!$input.attr('checked')) {
                    cy.contains('Green').click();

                    cy.wait('@changeVariant').its('response.statusCode').should('equal', 200);
                    cy.contains('.product-detail-price', '333.00');
                } else {
                    cy.log('Variant "Green" is already open.');
                    cy.contains('.product-detail-price', '333.00');
                }
            });
        });

        // Check usual price in "Red"
        cy.contains('Red').click();

        cy.wait('@changeVariant').its('response.statusCode').should('equal', 200);
        cy.contains('.product-detail-price', '64.00');
    });
});
