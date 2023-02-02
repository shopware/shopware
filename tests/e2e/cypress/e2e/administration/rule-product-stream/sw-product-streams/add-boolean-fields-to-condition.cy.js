// / <reference types='Cypress' />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Dynamic product group: Add Boolean fields to condition', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'First product',
            productNumber: 'RS-11111',
            active: null,
            description: 'Pudding wafer apple pie fruitcake cupcake.',
        }).then(() => {
            cy.createProductFixture({
                name: 'Second product',
                productNumber: 'RS-22222',
                active: false,
                description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping.',
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Third product',
                productNumber: 'RS-33333',
                active: true,
                description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping.',
            });
        }).then(() => {
            return cy.createDefaultFixture('custom-field-set', {
                customFields: [
                    {
                        active: true,
                        name: 'my_custom_boolean_field',
                        type: 'bool',
                        config: {
                            componentName: 'sw-field',
                            customFieldPosition: 1,
                            customFieldType: 'checkbox',
                            type: 'checkbox',
                            helpText: {
                                'en-GB': 'helptext',
                            },
                            label: {
                                'en-GB': 'my_custom_boolean_field',
                            },
                        },
                    },
                ],
            });
        }).then(() => {
            return cy.createDefaultFixture('product-stream');
        });
    });

    it('@base @rule: can preview products with boolean field', { tags: ['pa-business-ops'] }, () => {
        const productStreamPage = new ProductStreamObject();
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productStreamPage.elements.contextMenuButton,
            `${productStreamPage.elements.dataGridRow}--0`,
        );

        cy.contains(productStreamPage.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'Yes',
            },
        );

        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-modal').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (1)');
            cy.contains(`.sw-data-grid ${productStreamPage.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Third product');
            cy.get('.sw-modal__close').click();
        });

        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'No',
            },
        );

        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-modal').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (2)');
            cy.contains(`.sw-data-grid ${productStreamPage.elements.dataGridRow} .sw-data-grid__cell--name`, 'First product');
            cy.contains(`.sw-data-grid ${productStreamPage.elements.dataGridRow} .sw-data-grid__cell--name`, 'Second product');
            cy.get('.sw-modal__close').click();
        });
    });

    it('@base @rule: can preview products with custom field is boolean', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductPageObject();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'POST',
        }).as('saveCustomFieldSet');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // click on the custom field
        cy.contains(`.sw-grid-row${page.elements.gridRow}--0 a`, 'My custom field')
            .click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // check if the custom field is loaded before editing it
        cy.get(page.elements.loader).should('not.exist');

        // select "Products" from the multi select
        cy.get('.sw-settings-custom-field-set-detail-base__label-entities')
            .typeMultiSelectAndCheck('Products');

        // save the custom field
        cy.get('.sw-settings-set-detail__save-action').click();
        cy.wait('@saveCustomFieldSet').its('response.statusCode').should('equals', 200);

        cy.get('.sw-settings-set-detail__save-action').should('not.be.disabled');

        cy.get(`${page.elements.gridRow}--0 .sw-grid-column:nth-of-type(3)`).contains('my_custom_boolean_field');

        cy.get(`${page.elements.gridRow}--0 .sw-grid-column:nth-of-type(4)`).contains('Checkbox');

        cy.get(`${page.elements.gridRow}--0 .sw-grid-column:nth-of-type(5)`).contains('1');

        // go to product listing
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // open the first product
        cy.contains(`${page.elements.dataGridRow} .sw-data-grid__cell--name div > a`, 'First product')
            .click();

        // check if user is one the product page and everything is loaded
        cy.get('input[name=sw-field--product-name').scrollIntoView();
        cy.get('input[name=sw-field--product-name').should('be.visible');
        cy.get('.sw-product-detail__save-action').should('not.be.disabled');

        // go to specifications tab
        cy.get('.sw-tabs-item.sw-product-detail__tab-specifications').click();

        // scroll to input field
        cy.get('.sw-product-detail-specification__custom-fields').scrollIntoView();
        cy.get('.sw-form-field-renderer-input-field__my_custom_boolean_field input').scrollIntoView().click();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        cy.get('.sw-product-detail__save-button-group').click();
        cy.wait('@saveProduct').its('response.statusCode').should('equals', 200);

        const productStreamPage = new ProductStreamObject();
        cy.visit(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productStreamPage.elements.contextMenuButton,
            `${productStreamPage.elements.dataGridRow}--0`,
        );
        cy.contains(productStreamPage.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'my_custom_boolean_field',
                operator: null,
                value: 'No',
            },
        );

        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-modal').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (2)');
            cy.contains(`.sw-data-grid ${page.elements.dataGridRow} .sw-data-grid__cell--name`, 'Second product');
            cy.contains(`.sw-data-grid ${page.elements.dataGridRow} .sw-data-grid__cell--name`, 'Third product');
            cy.get('.sw-modal__close').click();
        });

        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'my_custom_boolean_field',
                operator: null,
                value: 'Yes',
            },
        );

        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-modal').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (1)');
            cy.contains(`.sw-data-grid ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'First product');
            cy.get('.sw-modal__close').click();
        });
    });
});
