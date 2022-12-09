// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.createProductVariantFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@catalogue: duplicate product with variants and inherited fields in listing', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/**/search/product`,
            method: 'POST'
        }).as('getProduct');


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
        cy.contains('.sw-product-variants-overview__single-variation', 'Red').click();
        cy.contains('.sw-product-variant-info__product-name', 'Variant product name');

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
        cy.get('#sw-field--product-name').scrollIntoView().should('be.visible');

        cy.get('.sw-field .icon--regular-link-horizontal').first().click();
        cy.get('#sw-field--product-name').clearTypeAndCheck('Variant in Red');
        cy.get('.sw-text-editor__content-editor').type('This is not an inherited variant text.');
        cy.contains('.sw-text-editor__content-editor', 'This is not an inherited variant text.');

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();

        // Verify inheritance config in listing
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`,
            'Variant product name');


        // Duplicate product by using context menu option
        cy.clickContextMenuItem(
            '.sw-product-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify product
        cy.url().should('contain', '/product/detail/');
        cy.wait('@duplicateProduct').its('response.statusCode').should('equal', 200);
        cy.wait('@getProduct').its('response.statusCode').should('equal', 200);
        cy.contains('.sw-product-variant-info__product-name', 'Variant product name Copy')
            .should('be.visible');
    });

    it('@catalogue: duplicate product with variants and inherited fields in detail', { tags: ['pa-inventory'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/**/search/product`,
            method: 'POST'
        }).as('getProduct');


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
        cy.contains('.sw-product-variants-overview__single-variation', 'Red').click();
        cy.contains('.sw-product-variant-info__product-name', 'Variant product name');

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
        cy.get('#sw-field--product-name').scrollIntoView().should('be.visible');
        cy.get('.sw-field .icon--regular-link-horizontal').first().click();
        cy.get('#sw-field--product-name').clearTypeAndCheck('Variant in Red');
        cy.get('.sw-text-editor__content-editor').type('This is not an inherited variant text.');
        cy.contains('.sw-text-editor__content-editor', 'This is not an inherited variant text.');

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();

        // Verify inheritance config in listing
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`,
            'Variant product name');

        // Open product to duplicate
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.wait('@getProduct').its('response.statusCode').should('equal', 200);

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-product-detail__save-button-group .sw-context-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').its('response.statusCode').should('equal', 200);
        cy.get('.clone-variant__modal').should('not.exist');
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'Variant product name Copy'
        );
    });
});
