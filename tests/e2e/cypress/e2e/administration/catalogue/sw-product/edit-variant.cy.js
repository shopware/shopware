/// <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.createProductVariantFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @catalogue: variants display corresponding name based on specific language', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('searchUserConfig');

        cy.visit(`${Cypress.env('admin')}#/sw/property/index`);

        // Add option to property group
        cy.wait('@searchUserConfig').its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

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
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.contains('.sw-data-grid__body', 'Rot');
        cy.contains('.sw-data-grid__body', 'Grün');

        // Switch to English
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.contains('.sw-select-result-list__item-list .sw-select-option--1', 'English');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.contains('.sw-data-grid__body', 'Red');
        cy.contains('.sw-data-grid__body', 'Green');

        cy.reload();

        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.contains('.sw-data-grid__body', 'Red');
        cy.contains('.sw-data-grid__body', 'Green');
    });

    it('@base @catalogue: add multidimensional variant to product', { tags: ['pa-inventory'] }, () => {
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
        cy.contains('.sw-button--ghost', 'Generate variants').should('be.visible').click();

        // Add another group to create a multidimensional variant
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Size', [0, 1, 2], 6);
        cy.get('.sw-product-variants__generate-action').should('be.visible');
        cy.get('.sw-product-variants__generate-action').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        cy.get('.sw-product-variants-overview').should('be.visible');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Variant product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Variant product name')
            .click();
        cy.contains('.product-detail-name', 'Variant product name');
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

    // TODO: Unskip with NEXT-15469, the restriction must be configured while creating the variants and not afterwards
    it('@base @catalogue: test multidimensional variant with restrictions', { tags: ['quarantined', 'pa-inventory'] }, () => {
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

        // Create and verify multi-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants__generate-action').should('be.visible');
        cy.get('.sw-product-variants__generate-action').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Size', [0, 1, 2], 9);
        cy.get('.sw-product-modal-variant-generation').should('not.exist');

        // Create and verify multi-dimensional variant
        cy.contains('.sw-button', 'Generate variants').should('be.visible').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        cy.get('.sw-variant-modal__restriction-configuration').click();
        cy.contains('.sw-button', 'Exclude values').click();
        cy.get('.sw-product-variants-configurator-restrictions__modal-main').should('be.visible');

        cy.get('#sw-field--selectedGroup').select('Size');
        cy.get('.sw-product-restriction-selection__select-option-wrapper .sw-multi-select')
            .typeMultiSelectAndCheck('M');
        cy.contains('.sw-product-variants-configurator-restrictions__modal-main > .sw-button', 'And').click();

        cy.get('.sw-product-restriction-selection:nth-of-type(2)').should('be.visible');
        cy.get('.sw-product-restriction-selection:nth-of-type(2) #sw-field--selectedGroup').select('Color');
        cy.get('.sw-product-restriction-selection:nth-of-type(2) .sw-product-restriction-selection__select-option-wrapper .sw-multi-select')
            .typeMultiSelectAndCheck('Red');

        cy.get('.sw-product-variants-configurator-restrictions__modal .sw-button--primary').click();

        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.contains('.sw-label:nth-of-type(1)', 'Red');
        cy.contains('.sw-label:nth-of-type(2)', 'M');
        cy.get('.sw-product-variant-generation__generate-action').click();
    });

    // TODO: unskip the test with NEXT-19240 - was skipped because it is extremely flaky
    it('@base @catalogue: test surcharges / discounts in variant', { tags: ['quarantined', 'pa-inventory'] }, () => {
        const page = new ProductPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            method: 'GET',
            url: `${Cypress.config('baseUrl')}/detail/**/switch?options=*`,
        }).as('changeVariant');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.contains('.sw-button--ghost', 'Generate variants').should('be.visible').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        page.generateVariants(
            'Size',
            [0, 1, 2],
            6,
            [[0, 3, 'gross', '10'], [2, 3, 'gross', '-10']],
        );

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Variant product name');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Variant product name')
            .click();

        cy.get('.product-detail-configurator-option-label[title="L"]').should('be.visible').click();
        cy.wait('@changeVariant').its('response.statusCode').should('equal', 200);
        cy.contains('.product-detail-price', '74');

        cy.get('.product-detail-configurator-option-label[title="M"]').should('be.visible').click();
        cy.wait('@changeVariant').its('response.statusCode').should('equal', 200);
        cy.contains('.product-detail-price', '64');

        cy.get('.product-detail-configurator-option-label[title="S"]').should('be.visible').click();
        cy.wait('@changeVariant').its('response.statusCode').should('equal', 200);
        cy.contains('.product-detail-price', '54');
    });
});
