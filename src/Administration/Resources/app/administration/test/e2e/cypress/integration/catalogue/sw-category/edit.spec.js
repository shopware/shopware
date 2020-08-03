/// <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Category: Edit categories', () => {
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState();
    });

    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-valid');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@catalogue: change content language without selected category', () => {
        const page = new CategoryPageObject();

        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home');

        page.changeTranslation('Deutsch', 1);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-empty-state__element').should('be.visible');

        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home');

        page.changeTranslation('English', 1);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-empty-state__element').should('be.visible');

        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home');
    });

    it('@catalogue: change content language with selected category', () => {
        const page = new CategoryPageObject();

        cy.get('.sw-tree-item__label')
            .contains('Home')
            .click();

        cy.get('#categoryName')
            .should('be.visible')
            .should('have.value', 'Home');

        page.changeTranslation('Deutsch', 1);
        cy.get('.sw-loader').should('not.exist');
        cy.get('#categoryName')
            .should('be.visible')
            .should('have.value', 'Home');
    });

    it('@catalogue: assign dynamic product group', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next9278')) {
                return;
            }

            cy.server();
            cy.route({
                url: '/api/v*/category/*',
                method: 'patch'
            }).as('saveData');

            // Select a category
            cy.get('.sw-tree-item__label')
                .contains('Catalogue #1')
                .click();

            // Scroll to product assignment
            cy.get('.sw-category-detail-base__products')
                .scrollIntoView();

            // Change product assignment type to dynamic product group
            cy.get('.sw-category-detail__product-assignment-type-select').typeSingleSelect(
                'Dynamic product group',
                '.sw-category-detail__product-assignment-type-select'
            );

            // Verify that the preview shows an empty state first
            cy.get('.sw-product-stream-grid-preview .sw-empty-state__title')
                .should('contain', 'No dynamic product group selected');

            // Select product stream
            cy.get('.sw-category-detail__product-stream-select').typeSingleSelect(
                '2nd Product stream',
                '.sw-category-detail__product-stream-select'
            );

            // Save the category
            cy.get('.sw-category-detail__save-action').click();

            // Wait for category request with correct data to be successful
            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
                expect(xhr.requestBody).to.have.property('productAssignmentType', 'product_stream');
                expect(xhr.requestBody).to.have.property('productStreamId');
            });

            // Verify configured data is correct after save action
            cy.get('.sw-category-detail__product-assignment-type-select .sw-single-select__selection-text')
                .should('contain', 'Dynamic product group');

            cy.get('.sw-category-detail__product-stream-select .sw-entity-single-select__selection-text')
                .should('contain', '2nd Product stream');
        });
    });
});
