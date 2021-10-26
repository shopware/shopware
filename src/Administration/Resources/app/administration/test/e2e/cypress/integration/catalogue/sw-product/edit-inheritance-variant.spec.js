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

    it('@catalogue: check fields in inheritance', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
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
        cy.get('#sw-field--product-name').scrollIntoView().should('be.visible');
        cy.get('#sw-field--product-name').clearTypeAndCheck('Variant in Red');
        cy.get('.sw-text-editor__content-editor').type('This is not an inherited variant text.');
        cy.get('.sw-text-editor__content-editor').contains('This is not an inherited variant text.');

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();

        // Verify inheritance config in listing
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Variant product name');
        cy.get('.sw-product-list__variant-indicator').should('be.visible');
        cy.get('.sw-product-list__variant-indicator').click();

        cy.get('.sw-modal').should('be.visible');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--name', 'Variant in Red')
            .should('be.visible');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--name a', 'Variant in Red')
            .click();

        // Verify inheritance config in detail
        cy.get('.sw-modal').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-text-editor__content-editor').contains('This is not an inherited variant text.');
    });

    it('@catalogue: duplicate product with variants and inherited fields in listing', () => {
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
        cy.get('#sw-field--product-name').scrollIntoView().should('be.visible');
        cy.get('#sw-field--product-name').clearTypeAndCheck('Variant in Red');
        cy.get('.sw-text-editor__content-editor').type('This is not an inherited variant text.');
        cy.get('.sw-text-editor__content-editor').contains('This is not an inherited variant text.');

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();

        // Verify inheritance config in listing
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Variant product name');


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

    it('@catalogue: duplicate product with variants and inherited fields in detail', () => {
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
        cy.get('#sw-field--product-name').scrollIntoView().should('be.visible');
        cy.get('#sw-field--product-name').clearTypeAndCheck('Variant in Red');
        cy.get('.sw-text-editor__content-editor').type('This is not an inherited variant text.');
        cy.get('.sw-text-editor__content-editor').contains('This is not an inherited variant text.');

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();

        // Verify inheritance config in listing
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Variant product name');

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
        cy.get('.clone-variant__modal').should('be.visible');
        cy.get('.clone-variant__modal').should('not.exist');
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'Variant product name Copy'
        );
    });
});
