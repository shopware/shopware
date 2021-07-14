// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import PropertyPageObject from '../../../support/pages/module/sw-property.page-object';

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

    it('@base @catalogue: variants display corresponding name based on specific language', () => {
        const page = new PropertyPageObject();

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('searchUserConfig');

        cy.visit(`${Cypress.env('admin')}#/sw/property/index`);

        // Add option to property group
        cy.wait('@searchUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.clickContextMenuItem(
                '.sw-property-list__edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );
        });

        cy.get(page.elements.cardTitle).contains('Basic information');

        // Switch language to Deutsch
        cy.get('.sw-language-switch__select .sw-entity-single-select__selection-text').contains('English');
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        // poor assertion to check if there is more than 1 language
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length.greaterThan', 1);
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .contains('Deutsch').click();

        // Edit and update property option's name for Deutsch
        cy.get('.sw-property-option-list').scrollIntoView();

        const redOption = cy.get('.sw-property-option-list').contains('Red').parents('tr');
        redOption.dblclick();
        redOption.get('#sw-field--item-name').typeAndCheck('Rot');
        redOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        const greenOption = cy.get('.sw-property-option-list').contains('Green').parents('tr');
        greenOption.dblclick();
        greenOption.get('#sw-field--item-name').typeAndCheck('Grün');
        greenOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        const productPage = new ProductPageObject();

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productPage.elements.contextMenuButton,
            `${productPage.elements.dataGridRow}--0`
        );
        cy.get('.sw-product-detail__tab-variants').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Rot');
        cy.get('.sw-data-grid__body').contains('Grün');

        // Switch to English
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').contains('English');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Green');

        cy.reload();

        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Green');
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

    it('@catalogue: check fields in inheritance', () => {
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
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        // Check field inheritance in variant
        cy.get('.sw-product-variants-overview__single-variation').contains('Red').click();
        cy.get('.sw-product-variant-info__product-name').contains('Variant product name');

        cy.get('.sw-product-basic-form__inheritance-wrapper-description')
            .find('.sw-inheritance-switch--is-inherited')
            .scrollIntoView()
            .should('be.visible');

        // remove inheritance
        cy.get('.sw-product-basic-form__inheritance-wrapper-description')
            .find('.sw-inheritance-switch--is-inherited')
            .scrollIntoView()
            .click();


        // check if inheritance is removed
        cy.get('.sw-product-basic-form__inheritance-wrapper-description')
            .find('.sw-inheritance-switch--is-not-inherited')
            .scrollIntoView()
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

    // TODO: Unskip with NEXT-15469, the restriction must be configured while creating the variants and not afterwards
    it.skip('@base @catalogue: test multidimensional variant with restrictions', () => {
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
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
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
        cy.contains('.sw-button', 'Generate variants').click();
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
        cy.get('.sw-label:nth-of-type(1)').contains('Red');
        cy.get('.sw-label:nth-of-type(2)').contains('M');
        cy.get('.sw-product-variant-generation__generate-action').click();
    });
});
