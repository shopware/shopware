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

        cy.onlyOnFeature('FEATURE_NEXT_17546', () => {
            // Check inherited price fields
            cy.get('.sw-product-detail-base__prices').scrollIntoView().should('be.visible');
            cy.get('.sw-list-price-field__price .sw-price-field__gross.is--disabled input')
                .should('have.value', 64);
            cy.get('.sw-list-price-field__purchase-price .sw-price-field__gross.is--disabled input')
                .should('have.value', 10);
            cy.get('.sw-list-price-field__list-price .sw-price-field__gross.is--disabled input')
                .should('have.value', 100);
        });

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

        cy.onlyOnFeature('FEATURE_NEXT_17546', () => {
            // Check price fields without being inherited
            cy.get('.sw-product-price-form__price-list > .sw-inherit-wrapper__toggle-wrapper')
                .find('.sw-inheritance-switch--is-inherited')
                .click();
            cy.get('.sw-product-detail-base__prices').scrollIntoView().should('be.visible');
            cy.get('.sw-list-price-field__price .sw-price-field__gross input')
                .should('be.enabled')
                .and('have.value', 64);
            cy.get('.sw-list-price-field__purchase-price .sw-price-field__gross input')
                .should('be.enabled')
                .and('have.value', 10);
            cy.get('.sw-list-price-field__list-price .sw-price-field__gross input')
                .should('be.enabled')
                .and('have.value', 100);
        });

        // Check other values
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
});
