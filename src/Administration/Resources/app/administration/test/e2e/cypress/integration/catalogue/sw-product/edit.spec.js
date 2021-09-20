/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit in various ways', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: edit a product\'s translation', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        page.changeTranslation('Deutsch', 0);
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw_language-info__info').contains('"Product name" displayed in the content language');
        cy.get('.sw_language-info__info').contains('span', '"Deutsch"');
        cy.get('.sw_language-info__info').contains('Fallback is the system default language');
        cy.get('input[name=sw-field--product-name]').type('Sauerkraut');
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 200);

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Sauerkraut');
    });

    it('@catalogue: edit product via inline edit', () => {
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'PATCH'
        }).as('saveData');

        // Inline edit customer
        cy.get('.sw-data-grid__cell--productNumber').dblclick();
        cy.get('#sw-field--item-name').should('be.visible');
        cy.get('#sw-field--item-name').should('have.value', 'Product name');

        cy.get('#sw-field--item-name')
            .clear()
            .should('have.value', '')
            .typeAndCheck('That\'s not my name');
        cy.get('.sw-data-grid__inline-edit-save').click();
        cy.awaitAndCheckNotification('Product "That\'s not my name" has been saved.');

        // Verify updated product
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-data-grid__cell--name').contains('That\'s not my name');
    });

    it('@base @catalogue: edit a product\'s custom field translation', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');


        // Access custom field
        cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);
        cy.contains('.sw-grid__cell-content a', 'My custom field').should('be.visible');
        cy.contains('.sw-grid__cell-content a', 'My custom field').click();

        // Assign custom field to products
        cy.get('#sw-field--set-name').should('be.visible');
        cy.get('.sw-settings-custom-field-set-detail-base__label-entities').typeMultiSelectAndCheck('Products');
        cy.contains('.sw-label', 'Products').should('be.visible');

        cy.get('.sw-settings-set-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        // Open product
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-specifications').should('be.visible');
        cy.get('.sw-product-detail__tab-specifications').click();

        // Fill custom field in english
        cy.get('#custom_field_set_property').scrollIntoView();
        cy.get('#custom_field_set_property').type('EN');

        // Change
        page.changeTranslation('Deutsch', 0);
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body > p')
            .contains('There are unsaved changes in the current language. Do you want to save them now?');
        cy.get('#sw-language-switch-save-changes-button').click();
        cy.get('.sw_modal').should('not.exist');
        cy.get(page.elements.loader).should('not.exist');

        // Fill custom field in german
        cy.get('#custom_field_set_property').scrollIntoView();
        cy.get('#custom_field_set_property').type('DE');

        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 200);
    });
});
