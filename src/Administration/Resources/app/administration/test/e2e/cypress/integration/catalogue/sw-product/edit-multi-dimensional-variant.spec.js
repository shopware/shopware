// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createProductVariantFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: add multidimensional variant to product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.contains('.sw-button--ghost', 'Generate variants').click();

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
        cy.get('.search-suggest-product-name')
            .contains('Variant product name')
            .click();
        cy.get('.product-detail-name').contains('Variant product name');
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

    it('@base @catalogue: test multidimensional variant with diversification', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'post'
        }).as('loadCategory');
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-category-form').scrollIntoView();
        cy.get('.sw-category-tree__input-field').should('be.visible');
        cy.get('.sw-category-tree__input-field').click();
        cy.get('.sw-category-tree__input-field').type('Home');
        cy.wait('@loadCategory').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.wait('@loadCategory').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-category-tree__input-field').type('{enter}');
        });

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.contains('.sw-label', 'Home').should('be.visible');

        cy.get('.sw-product-detail__tab-variants').scrollIntoView();
        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.ghostButton, 'Generate variants')
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Request we want to wait for later
        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post'
        }).as('loadPropertyGroup');

        page.generateVariants('Size', [0, 1, 2], 6);

        // Reload the variant tab to avoid xhr timing issues from previous requests
        cy.get('.sw-product-detail__tab-variants').click();

        cy.get(page.elements.loader).should('not.exist');

        // Wait for every needed xhr request to load the current product
        // `@searchCall` was defined in `page.generateVariants`
        cy.wait(['@searchCall', '@loadPropertyGroup'])
            .then((xhrs) => {
                xhrs.forEach((xhr) => {
                    expect(xhr).to.have.property('status', 200);
                });
            });

        cy.get('.sw-product-variants-overview').should('be.visible');

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

        cy.contains('.sw-field__label', 'Color').click();
        cy.contains('.sw-field__label', 'Size').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-modal').should('not.exist');

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-box').its('length').should('be.gt', 5);
        cy.get('.product-variant-characteristics').contains('Color: Red | Size: S');
        cy.get('.product-variant-characteristics').contains('Color: Green | Size: L');
    });

    it('@base @catalogue: test multidimensional variant with restrictions', () => {
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
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/product/**/combinations`,
            method: 'get'
        }).as('combinationCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post'
        }).as('searchCall');
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.contains('.sw-button', 'Generate variants')
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify multi-dimensional variant
        cy.contains('.group_grid__column-name', propertyName).click();
        for (const entry in Object.values(optionPosition)) { // eslint-disable-line
            if (optionPosition.hasOwnProperty(entry)) {
                cy.get(
                    `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`
                ).click();
            }
        }
        cy.get(`.sw-grid ${optionsIndicator}`)
            .contains(`${optionPosition.length} ${optionString} selected`);
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
        cy.get('.sw-product-variant-generation__generate-action').click();
        cy.get('.sw-product-modal-variant-generation__notification-modal').should('be.visible');
        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-modal__body')
            .contains('5 variants will be added');
        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-button--primary')
            .click();

        cy.wait('@combinationCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Inspect variant generation
        cy.get('.sw-product-modal-variant-generation__notification-modal').should('not.exist');
        cy.wait('@searchCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-product-modal-variant-generation').should('not.exist');

        // Verify variant restrictions
        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 5);
    });
});
