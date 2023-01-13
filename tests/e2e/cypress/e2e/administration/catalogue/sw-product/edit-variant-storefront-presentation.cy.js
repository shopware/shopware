/// <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test Storefront presentation of variants', () => {
    beforeEach(() => {
        cy.createProductVariantFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @catalogue: test multidimensional variant with diversification', {tags: ['pa-inventory']}, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST',
        }).as('propertyGroup');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'PATCH',
        }).as('savePresentation');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-category-form').scrollIntoView();
        cy.get('.sw-category-tree__input-field').should('be.visible');
        cy.get('.sw-category-tree__input-field').click();
        cy.get('.sw-category-tree__input-field').type('Home');
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);

        cy.contains('.sw-highlight-text__highlight', 'Home').should('be.visible');
        cy.get('.sw-category-tree__input-field').type('{enter}');
        cy.contains('.sw-label', 'Home').should('be.visible');
        cy.get('.sw-category-tree__input-field').type('{esc}');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.successIcon).should('be.visible');

        cy.get('.sw-product-detail__select-category').scrollIntoView();
        cy.contains('.sw-label', 'Home').should('be.visible');

        cy.get('.sw-product-detail__tab-variants').scrollIntoView();
        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.ghostButton, 'Generate variants')
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        page.generateVariants('Size', [0, 1, 2], 6);

        // Reload the variant tab to avoid xhr timing issues from previous requests
        cy.get('.sw-product-detail__tab-variants').click();

        cy.get(page.elements.loader).should('not.exist');

        // Wait for every needed xhr request to load the current product
        // `@searchCall` was defined in `page.generateVariants`
        cy.wait('@searchCall').its('response.statusCode').should('equal', 200);
        cy.wait('@propertyGroup').its('response.statusCode').should('equal', 200);

        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.wait('@propertyGroup')
            .its('response.statusCode')
            .should('equal', 200);

        // Activate diversification
        cy.get('.sw-product-variants__configure-storefront-action').click();
        cy.get('.sw-modal').should('be.visible');
        cy.contains('Product listings').click();

        cy.get('.sw-product-variants-delivery-listing-config-options').should('be.visible');

        // Verify 'Expand property values in product listings' is checked
        cy.contains('.sw-field__radio-option > label', 'Expand property values in product listings')
            .invoke('attr', 'for')
            .then((id) => {
                cy.get(`#${id}`);
            })
            .click()
            .should('be.checked');

        cy.get('.sw-product-variants-delivery-listing-config > :nth-child(2) input').first().click();
        cy.get('.sw-product-variants-delivery-listing-config > :nth-child(2) input').first().should('be.checked');
        cy.get('.sw-product-variants-delivery-listing-config > :nth-child(2) input').last().click();
        cy.get('.sw-product-variants-delivery-listing-config > :nth-child(2) input').last().should('be.checked');
        cy.get('.sw-modal .sw-button--primary').click();
        cy.wait('@savePresentation').its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal').should('not.exist');

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-box').its('length').should('be.gt', 5);
        cy.contains('.product-variant-characteristics', 'Color: Red | Size: S');
        cy.contains('.product-variant-characteristics', 'Color: Green | Size: L');
    });

    it('@catalogue: test main variant presentation with parent and variant given', {tags: ['pa-inventory']}, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post',
        }).as('searchCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post',
        }).as('propertyGroup');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'PATCH',
        }).as('savePresentation');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-category-form').scrollIntoView();
        cy.get('.sw-category-tree__input-field').should('be.visible');
        cy.get('.sw-category-tree__input-field').click();
        cy.get('.sw-category-tree__input-field').type('Home');
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);

        cy.contains('.sw-highlight-text__highlight', 'Home').should('be.visible');
        cy.get('.sw-category-tree__input-field').type('{enter}');
        cy.contains('.sw-label', 'Home').should('be.visible');
        cy.get('.sw-category-tree__input-field').type('{esc}');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.successIcon).should('be.visible');

        cy.get('.sw-product-detail__select-category').scrollIntoView();
        cy.contains('.sw-label', 'Home').should('be.visible');

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get('.sw-product-detail__tab-variants').scrollIntoView();

        // Reload the variant tab to avoid xhr timing issues from previous requests
        cy.get(page.elements.loader).should('not.exist');

        // Wait for every needed xhr request to load the current product
        cy.wait('@searchCall')
            .its('response.statusCode').should('equal', 200);
        cy.wait('@searchCall')
            .its('response.statusCode').should('equal', 200);
        cy.wait('@propertyGroup')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');

        // Activate main variant visualization
        cy.get('.sw-product-variants__configure-storefront-action').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-tabs-item', 'Product listings').click();
        cy.get('.sw-product-variants-delivery-listing-config-options').should('be.visible');

        cy.get('.sw-product-variants-delivery-listing_entity-select.is--disabled')
            .should('not.exist');
        cy.get('.sw-product-variants-delivery-listing-config-options.is--disabled')
            .last().should('be.visible');

        cy.contains('.sw-product-variants-delivery-listing-config .sw-field__radio-option-label span', 'Variant')
            .should('be.visible');
        cy.get('sw-product-variants-delivery-listing_entity-select.is--disabled')
            .should('not.exist');
        cy.get('#mainVariant').typeSingleSelectAndCheck('Green', '#mainVariant');
        cy.contains('.sw-modal__footer .sw-button', 'Save').click();
        cy.wait('@savePresentation').its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal').should('not.exist');

        // Verify in storefront
        cy.visit('/');
        cy.contains('.product-name', 'Variant product name').should('be.visible');
        cy.contains('.product-name', 'Variant product name').click();

        cy.url().should('contain', '/Variant-product-name/RS-333');
        cy.contains('h1', 'Green variant product name').should('be.visible');
    });

    it('@catalogue: test main variant presentation with parent but without variant given', {tags: ['pa-inventory']}, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('searchCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST',
        }).as('propertyGroup');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'PATCH',
        }).as('savePresentation');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-category-form').scrollIntoView();
        cy.get('.sw-category-tree__input-field').should('be.visible');
        cy.get('.sw-category-tree__input-field').click();
        cy.get('.sw-category-tree__input-field').type('Home');
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);

        cy.contains('.sw-highlight-text__highlight', 'Home').should('be.visible');
        cy.get('.sw-category-tree__input-field').type('{enter}');
        cy.contains('.sw-label', 'Home').should('be.visible');
        cy.get('.sw-category-tree__input-field').type('{esc}');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.successIcon).should('be.visible');

        cy.get('.sw-product-detail__select-category').scrollIntoView();
        cy.contains('.sw-label', 'Home').should('be.visible');
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');
        cy.contains('.product-name', 'Variant product name').should('be.visible');
        cy.contains('.product-name', 'Variant product name').click();

        cy.url().should('contain', '/Variant-product-name');
        cy.contains('h1', 'Variant product name').should('be.visible');
    });
});

describe('Product: Test Storefront presentation of variants different setup', () => {
    beforeEach(() => {
        cy.searchViaAdminApi({
            data: {
                field: 'name',
                value: 'Storefront',
            },
            endpoint: 'sales-channel',
        }).then((salesChannel) => {
            return cy.createDefaultFixture('product', {
                visibilities: [{
                    visibility: 30,
                    salesChannelId: salesChannel.id,
                }],
            }, 'product-variants-storefront.json');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@catalogue: test sorting of multidimensional variant order', {tags: ['pa-inventory']}, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('searchCall');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST',
        }).as('propertyGroup');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'PATCH',
        }).as('savePresentation');

        cy.get(':nth-child(1) > .sw-sidebar-navigation-item').should('be.visible');
        cy.get(':nth-child(1) > .sw-sidebar-navigation-item').click();

        // Navigate to variant generator listing and start
        cy.contains('.sw-data-grid__cell--name a', 'Test product').click();

        cy.get(page.elements.loader).should('not.exist');

        // Open storefront presentation settings
        cy.get('.sw-product-detail__tab-variants').click();
        cy.get('.sw-product-detail__tab-variants').scrollIntoView();

        cy.get(page.elements.loader).should('not.exist');

        // Reload the variant tab to avoid xhr timing issues from previous requests
        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');

        // Wait for every needed xhr request to load the current product
        cy.wait('@searchCall')
            .its('response.statusCode').should('equal', 200);
        cy.wait('@searchCall')
            .its('response.statusCode').should('equal', 200);
        cy.wait('@propertyGroup')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');

        // Activate main variant visualization
        cy.get('.sw-product-variants__configure-storefront-action').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        // Sort variant groups
        cy.get('.sw-tree-item').last().should('contain', 'size');
        cy.get('.sw-product-variants-delivery-order__groups > .sw-tree > .sw-tree__content > .tree-items > :nth-child(1) > .sw-tree-item__element')
            .dragTo('.sw-product-variants-delivery-order__groups > .sw-tree > .sw-tree__content > .tree-items > :nth-child(2) > .sw-tree-item__element');
        cy.get('.sw-tree-item').last().should('contain', 'Color');

        // Save product
        cy.contains('.sw-modal__footer .sw-button', 'Save').click();
        cy.wait('@savePresentation').its('response.statusCode').should('equal', 204);
        cy.get('.sw-modal').should('not.exist');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Test product');
        cy.get('.search-suggest-container').should('be.visible');
        cy.contains('.search-suggest-product-name', 'Test product')
            .click();

        cy.contains('h1', 'Test product').should('be.visible');
        cy.get('.product-detail-configurator-group-title').eq(0) // First Heading
            .should('contain', 'size');
        cy.get('.product-detail-configurator-group-title').eq(1) // Second Heading
            .should('contain', 'Color');
    });
});
