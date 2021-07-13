/// <reference types='Cypress' />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Add custom fields to condition', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            });
    });

    it('@visual: can create dynamic product group with custom field', () => {
        const page = new ProductPageObject();
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/custom-field-set`,
            method: 'post'
        }).as('saveCustomFieldSet');
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);

        // click on the custom field
        cy.get('.sw-grid-row.sw-grid__row--0 a')
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

        // go to product listing
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        // open the first product
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name div > a`)
            .contains('Product name')
            .click();

        // check if user is one the product page and everything is loaded
        cy.get('input[name=sw-field--product-name').scrollIntoView();
        cy.get('input[name=sw-field--product-name').should('be.visible');
        cy.get('.sw-product-detail__save-action').should('not.be.disabled');

        // go to specifications tab
        cy.get('.sw-tabs-item.sw-product-detail__tab-specifications').click();

        // scroll to input field
        cy.get('.sw-product-detail-specification__custom-fields').scrollIntoView();
        cy.get('input[name=custom_field_set_property').scrollIntoView();

        // type value
        cy.get('input[name=custom_field_set_property]').clear().type('custom field');

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
        productStreamPage.selectFieldAndOperator('@currentProductStreamFilter', 'custom_field_set_property', 'Is equal to');
        cy.get('input[name=sw-field--stringValue]').type('custom field');

        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (1)');
            cy.get('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Product name');
            cy.get('.sw-modal__close').click();
        });
    });
});
