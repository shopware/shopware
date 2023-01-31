// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.createProductVariantFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
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
        cy.contains('.sw-button--ghost', 'Generate variants')
            .should('be.visible')
            .click();

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
        cy.contains('.search-suggest-product-name','Variant product name')
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

    it('@base @catalogue: test multidimensional variant with restrictions', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();
        const optionsIndicator = '' +
            '.sw-property-search__tree-selection__column-items-selected.sw-grid-column--right span';
        const optionString = 'values';
        const multiSelect = '.sw-multi-select';
        const restrictionSelection =
            '.sw-product-restriction-selection:nth-of-type(2) .sw-product-restriction-selection__select-option-wrapper';

        const propertyName = 'Size';
        const optionPosition = [0, 1, 2];

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/product/**/combinations`,
            method: 'GET',
        }).as('combinationCall');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('searchCall');
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
        cy.contains('.sw-button', 'Generate variants')
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify multi-dimensional variant
        cy.contains('.group_grid__column-name', propertyName).click();
        for (const entry in Object.values(optionPosition)) {
            cy.get(
                `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`,
            ).click();
        }
        cy.contains(`.sw-grid ${optionsIndicator}`,`${optionPosition.length} ${optionString} selected`);
        cy.get('.sw-variant-modal__restriction-configuration').should('be.visible');
        cy.get('.sw-variant-modal__restriction-configuration').click();

        // Set two variant restrictions
        cy.get('.sw-product-variants-configurator-restrictions').should('be.visible');
        cy.contains('.sw-button--ghost', 'Exclude values').click();
        cy.get('.sw-product-variants-configurator-restrictions__modal').should('be.visible');

        cy.get('.sw-product-variants-configurator-restrictions__modal').should('be.visible');
        cy.get('#sw-field--selectedGroup').select('Size');
        cy.get('.sw-product-restriction-selection__select-option-wrapper .sw-multi-select').typeMultiSelectAndCheck('L');
        cy.contains('.sw-product-variants-configurator-restrictions__modal-main > .sw-button', 'And').click();

        cy.get('.sw-product-restriction-selection:nth-of-type(2)').should('be.visible');
        cy.get('.sw-product-restriction-selection:nth-of-type(2) #sw-field--selectedGroup').select('Color');
        cy.get(`${restrictionSelection} ${multiSelect}`)
            .typeMultiSelectAndCheck('Red');

        // Save restrictions
        cy.get('.sw-product-variants-configurator-restrictions__modal .sw-modal__footer .sw-button--primary').click();
        cy.get('.sw-product-variants-configurator-restrictions__modal').should('not.exist');
        cy.contains('.sw-data-grid__cell-content', 'Excluded property values').should('be.visible');
        cy.contains('.sw-product-variants-configurator-restrictions .sw-label', 'L').should('be.visible');

        // Generate variants with restrictions
        cy.get('.sw-product-variant-generation__next-action').click();
        cy.get('.sw-product-modal-variant-generation__upload_files').should('be.visible');
        cy.get('.sw-product-modal-variant-generation__infoBox').contains(new RegExp(`5 variants will be added`));
        cy.get('.sw-product-modal-variant-generation__upload_files .sw-button--primary').click();

        cy.wait('@combinationCall')
            .its('response.statusCode').should('equal', 200);

        // Inspect variant generation
        cy.get('.sw-product-modal-variant-generation__notification-modal').should('not.exist');
        cy.wait('@searchCall')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-product-modal-variant-generation').should('not.exist');

        // Verify variant restrictions
        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 5);
    });
});
