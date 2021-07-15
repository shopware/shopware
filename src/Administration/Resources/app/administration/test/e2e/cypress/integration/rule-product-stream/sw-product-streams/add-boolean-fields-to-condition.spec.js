// / <reference types='Cypress' />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';
import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Dynamic product group: Add Boolean fields to condition', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createProductFixture({
                    name: 'First product',
                    productNumber: 'RS-11111',
                    active: null,
                    description: 'Pudding wafer apple pie fruitcake cupcake.'
                }).then(() => {
                    cy.createProductFixture({
                        name: 'Second product',
                        productNumber: 'RS-22222',
                        active: false,
                        description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping.'
                    });
                }).then(() => {
                    cy.createProductFixture({
                        name: 'Third product',
                        productNumber: 'RS-33333',
                        active: true,
                        description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping.'
                    });
                });
            })
            .then(() => {
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
                                    'en-GB': 'helptext'
                                },
                                label: {
                                    'en-GB': 'my_custom_boolean_field'
                                }
                            }
                        }
                    ]
                });
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            });
    });

    it('@base @rule: can preview products with boolean field', () => {
        const productStreamPage = new ProductStreamObject();
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productStreamPage.elements.contextMenuButton,
            `${productStreamPage.elements.dataGridRow}--0`
        );
        cy.get(productStreamPage.elements.smartBarHeader).contains('1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'Yes'
            }
        );

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (1)');
            cy.get(`.sw-data-grid ${productStreamPage.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Third product');
            cy.get('.sw-modal__close').click();
        });

        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'No'
            }
        );

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (2)');
            cy.get(`.sw-data-grid ${productStreamPage.elements.dataGridRow} .sw-data-grid__cell--name`).contains('First product');
            cy.get(`.sw-data-grid ${productStreamPage.elements.dataGridRow} .sw-data-grid__cell--name`).contains('Second product');
            cy.get('.sw-modal__close').click();
        });
    });

    it('@base @rule: can preview products with custom field is boolean', () => {
        const page = new ProductPageObject();
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'post'
        }).as('saveCustomFieldSet');
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);

        // click on the custom field
        cy.get(`.sw-grid-row${page.elements.gridRow}--0 a`)
            .contains('My custom field')
            .click();

        // check if the custom field is loaded before editing it
        cy.get(page.elements.loader).should('not.exist');

        // select "Products" from the multi select
        cy.get('.sw-settings-custom-field-set-detail-base__label-entities')
            .typeMultiSelectAndCheck('Products');

        // save the custom field
        cy.get('.sw-settings-set-detail__save-action').click();
        cy.wait('@saveCustomFieldSet').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-settings-set-detail__save-action').should('not.be.disabled');

        cy.get(`${page.elements.gridRow}--0 .sw-grid-column:nth-of-type(3)`).contains('my_custom_boolean_field');

        cy.get(`${page.elements.gridRow}--0 .sw-grid-column:nth-of-type(4)`).contains('Checkbox');

        cy.get(`${page.elements.gridRow}--0 .sw-grid-column:nth-of-type(5)`).contains('1');

        // go to product listing
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        // open the first product
        cy.get(`${page.elements.dataGridRow} .sw-data-grid__cell--name div > a`)
            .contains('First product')
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

        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveProduct');

        cy.get('.sw-product-detail__save-button-group').click();
        cy.wait('@saveProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        const productStreamPage = new ProductStreamObject();
        cy.visit(`${Cypress.env('admin')}#/sw/product/stream/index`);
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productStreamPage.elements.contextMenuButton,
            `${productStreamPage.elements.dataGridRow}--0`
        );
        cy.get(productStreamPage.elements.smartBarHeader).contains('1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'my_custom_boolean_field',
                operator: null,
                value: 'No'
            }
        );

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (2)');
            cy.get(`.sw-data-grid ${page.elements.dataGridRow} .sw-data-grid__cell--name`).contains('Second product');
            cy.get(`.sw-data-grid ${page.elements.dataGridRow} .sw-data-grid__cell--name`).contains('Third product');
            cy.get('.sw-modal__close').click();
        });

        productStreamPage.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'my_custom_boolean_field',
                operator: null,
                value: 'Yes'
            }
        );

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (1)');
            cy.get(`.sw-data-grid ${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('First product');
            cy.get('.sw-modal__close').click();
        });
    });
});
