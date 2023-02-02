///  <reference types="Cypress" />
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';
import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';
import bulkEditVariants from '../../../../fixtures/bulk-edit-variants-list.json';

describe('Product: Bulk edit variants', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Variant Product',
            productNumber: 'Variant-1234',
            price: [{
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                linked: true,
                gross: 60,
            }],
        })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{name: 'Red'}, {name: 'Yellow'}, {name: 'Green'}],
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{name: 'S'}, {name: 'M'}, {name: 'L'}],
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/create`);
            });
    });

    it('@package @bulk-edit: should modify variant products with the bulk edit functionality', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('getProduct');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('getSalesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('getUserConfig');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/custom-field-set`,
            method: 'POST',
        }).as('saveCustomField');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST',
        }).as('saveDataFileUpload');

        const page = new ProductPageObject();
        const mediaPage = new MediaPageObject();
        const propertyValue = '.sw-property-search__tree-selection__option_grid';
        const gridRowOne = '[class="sw-data-grid__row sw-data-grid__row--0"]';
        const icon = '.sw-bulk-edit-change-field__container.sw-container .sw-inheritance-switch > .sw-icon > svg';

        // Create new custom field
        cy.get('.sw-settings-set-detail__save-action').should('be.enabled');
        cy.get('#sw-field--set-name').clearTypeAndCheck('custom_test');
        cy.get('.sw-custom-field-translated-labels input').clearTypeAndCheck('custom_test');
        cy.get('.sw-select').typeMultiSelectAndCheck('Products');
        cy.get('.sw-empty-state').should('exist');

        // Saving custom field
        cy.get('.sw-settings-set-detail__save-action').click();
        cy.wait('@saveCustomField').its('response.statusCode').should('equal', 204);

        cy.get('.sw-button.sw-button--small.sw-custom-field-list__add-button').click();
        cy.get('#sw-field--currentCustomField-config-customFieldType').select('Text field');
        cy.get('.sw-custom-field-detail__technical-name [type]').clearTypeAndCheck('custom_text');
        cy.get('.sw-button.sw-custom-field-detail__footer-save').click();
        cy.get('.sw-custom-field-list__custom-field-label').should('be.visible');
        cy.get('.sw-settings-set-detail__save-action').click();

        // Go to products page to create variant product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.wait('@getProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`);

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get('.sw-empty-state.sw-product-detail-variants__generated-variants-empty-state').should('be.visible');
        cy.contains('.sw-button--ghost', 'Generate variants').should('be.visible').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Color', [0, 1], 2);
        cy.get('.sw-product-variants__generate-action').should('be.visible');
        cy.get('.sw-product-variants__generate-action').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Size', [0, 1], 4);

        cy.get('.sw-product-variants-overview').should('be.visible');
        cy.get('.sw-skeleton').should('exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-card__title').should('be.visible');
        cy.reload();
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected-count').should('include.text', '4');
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.sw-data-grid__bulk-selected.bulk-link').click();

        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.url().should('include', 'bulk/edit/product');

        // Start bulk edit variant products
        cy.log('Start bulk edit variant products');
        cy.get('.sw-text-editor').should('be.visible');
        cy.get('.sw-bulk-edit-change-field-description [type]').click();
        cy.get('.sw-bulk-edit-change-field-description .sw-inheritance-switch > .sw-icon > svg').click();
        cy.get('.sw-text-editor__content-editor').clear().type(bulkEditVariants.description);

        // Tax rate
        cy.get('.sw-bulk-edit-change-field-taxId [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-taxId${icon}`).click();
        cy.get('[name="taxId"] .sw-block-field__block').typeSingleSelectAndCheck(bulkEditVariants.taxRate, '[name="taxId"] .sw-block-field__block');

        // Price
        cy.get('.sw-bulk-edit-change-field-isPriceInherited [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-isPriceInherited${icon}`).click();
        cy.get('#price-gross').clearTypeAndCheck(bulkEditVariants.price.gross);
        cy.get('#listPrice-gross').clearTypeAndCheck(bulkEditVariants.price.list);
        cy.get('#regulationPrice-gross').clearTypeAndCheck(bulkEditVariants.price.cheapest);

        // Advance pricing
        cy.get('.sw-bulk-edit-change-field-prices [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-prices${icon}`).click();
        cy.get('.sw-bulk-edit-change-field-prices .sw-card__quick-link').click();
        cy.get('#modalTitleEl').should('be.visible').and('include.text', 'Advanced pricing');
        cy.get('.sw-product-detail-context-prices__empty-state-select-rule')
            .typeSingleSelect('All customers', '.sw-product-detail-context-prices__empty-state-select-rule');
        cy.get('.sw-product-detail-context-prices__toolbar').should('be.visible');
        cy.get('[placeholder="∞"').should('be.visible').clearTypeAndCheck(bulkEditVariants.advance.no);
        cy.get('[placeholder="∞"').type('{enter}');
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().clearTypeAndCheck(bulkEditVariants.advance.price);
        cy.get(`${gridRowOne} .sw-price-field__gross [type]`).first()
            .focus().type('{enter}');
        cy.contains('Close').click();

        // Properties
        cy.get('.sw-bulk-edit-change-field-properties [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-properties${icon}`).click();
        cy.contains('Configure properties').click();
        cy.get('#modalTitleEl').should('be.visible');
        cy.contains('Size').click();
        cy.get(`${propertyValue} .sw-grid__cell-content`).should('be.visible');
        cy.get(`${propertyValue} .sw-grid__row--0 input`).click();
        cy.get(`${propertyValue} .sw-grid__row--1 input`).click();
        cy.get('.sw-property-search__tree-selection__column-items-selected').should('include.text', '2');
        cy.get('.sw-product-add-properties-modal__button-save').click();
        cy.get('.sw-data-grid__cell-value').should('include.text', 'Size');

        // Deliverability
        cy.get('.sw-bulk-edit-change-field-stock [type="checkbox"]').click();
        cy.get('input#stock').clearTypeAndCheck(bulkEditVariants.deliverability.stock);
        cy.get('.sw-bulk-edit-change-field-restockTime [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-restockTime${icon}`).click();
        cy.get('input#restockTime').clearTypeAndCheck(bulkEditVariants.deliverability.restock);

        // Visibility (Set product display at the storefront as "Hide in the Listings")
        cy.get('.sw-bulk-edit-change-field-visibilities [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-visibilities${icon}`).click();
        cy.get('.advanced-visibility').click();
        cy.get('#modalTitleEl').should('be.visible');
        cy.get('[type="radio"]').eq(1).check({force: true}).should('be.checked');
        cy.get('.sw-modal__footer .sw-button--primary').click();

        // Media
        cy.get('.sw-bulk-edit-change-field-media [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-media${icon}`).click();
        cy.setEntitySearchable('media', ['fileName', 'title']);
        mediaPage.uploadImageUsingFileUpload('img/sw-login-background.png');
        cy.wait('@saveDataFileUpload').its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('File has been saved.');

        // SEO
        cy.get('.sw-bulk-edit-change-field-metaTitle [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-metaTitle${icon}`).click();
        cy.get('input#metaTitle').clearTypeAndCheck(bulkEditVariants.seo);

        // Measures & packaging
        cy.get('.sw-bulk-edit-change-field-width [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-width${icon}`).click();
        cy.get('input#width').clearTypeAndCheck(bulkEditVariants.width);
        cy.get('.sw-bulk-edit-change-field-height [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-height${icon}`).click();
        cy.get('input#height').clearTypeAndCheck(bulkEditVariants.height);

        // Essential Characteristics
        cy.get('.sw-bulk-edit-change-field-featureSetId [type="checkbox"]').click();
        cy.get(`.sw-bulk-edit-change-field-featureSetId${icon}`).click();
        cy.get('[name="featureSetId"] .sw-block-field__block')
            .typeSingleSelectAndCheck(bulkEditVariants.essential, '[name="featureSetId"] .sw-block-field__block');

        // Custom fields
        cy.get('.sw-bulk-edit-custom-fields__change [type]').click();
        cy.get('.sw-container .icon--regular-link-horizontal.sw-icon > svg').last().click();
        cy.get('input#custom_text').clearTypeAndCheck(bulkEditVariants.custom);

        // Save and apply changes
        cy.log('Save and apply changes');
        cy.get('.sw-bulk-edit-product__save-action').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.footer-right .sw-button--primary').contains('Apply changes');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.get('.sw-bulk-edit-save-modal-success').should('be.visible');
        cy.get('.footer-right > .sw-button').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');

        // Verify the changes from the variant product details
        cy.log('Verify the changes from the variant products');
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button.sw-button--x-small.sw-product-list__variant-indicator').click();
        cy.get('.sw-data-grid__row.sw-data-grid__row--0 .sw-product-variant-info.sw-product-variant-modal__variant-options').click();
        cy.get('.sw-text-editor__content-editor').should('include.text', bulkEditVariants.description);
        cy.get('select#sw-field--product-taxId').contains('Super-reduced rate');
        cy.get('.sw-list-price-field__price-field [name="sw-price-field-gross"]').should('have.value', bulkEditVariants.price.gross);
        cy.get('.sw-list-price-field__list-price-field [name="sw-price-field-gross"]').should('have.value', bulkEditVariants.price.list);
        cy.get('.sw-list-price-field__regulation-price-field [name="sw-price-field-gross"]').should('have.value', bulkEditVariants.price.cheapest);
        cy.get('input#sw-field--product-stock').should('have.value', bulkEditVariants.deliverability.stock);
        cy.get('input#sw-field--product-restock-time').should('have.value', bulkEditVariants.deliverability.restock);

        // Media
        cy.get('.sw-media-preview-v2.sw-product-image__image').should('exist');

        // Specifications
        cy.contains('Specifications').click();
        cy.get('[placeholder="e.g. 500..."]').should('have.value', bulkEditVariants.width);
        cy.get('[placeholder="e.g. 200..."]').should('have.value', bulkEditVariants.height);
        cy.get('.sw-product-properties__card .sw-card__title').should('be.visible');
        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content').scrollIntoView().should('be.visible')
            .and('include.text', 'Size');
        cy.get('.sw-product-feature-set-form__form .sw-entity-single-select__selection').scrollIntoView()
            .should('include.text', bulkEditVariants.essential);
        cy.get('input#custom_text').should('have.value', bulkEditVariants.custom);

        // Advanced pricing
        cy.contains('Advanced pricing').click();
        cy.get('.sw-card__title').should('be.visible').contains('All customers');

        // SEO
        cy.contains('SEO').click();
        cy.get('[placeholder="Enter meta title..."]')
            .should('have.value', bulkEditVariants.seo);

        // Check visibility from the storefront (The product should NOT be seen in the listing)
        cy.visit('/');
        cy.get('.alert-content-container').should('be.visible').contains('No products found.');
    });
});
