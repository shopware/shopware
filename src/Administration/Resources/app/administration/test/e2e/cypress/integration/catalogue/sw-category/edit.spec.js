// / <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Category: Edit categories', () => {
    // eslint-disable-next-line no-undef
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

        page.changeTranslation('Deutsch', 0);
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

        page.changeTranslation('Deutsch', 0);
        cy.get('.sw-loader').should('not.exist');
        cy.get('#categoryName')
            .should('be.visible')
            .should('have.value', 'Home');
    });

    it('@catalogue: assign dynamic product group', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveData');

        // Select a category
        cy.get('.sw-tree-item__label')
            .contains('Home')
            .click();

        // Switch to products tab
        cy.get('.sw-category-detail__tab-products')
            .contains('Products')
            .click();

        // Change product assignment type to dynamic product group
        cy.get('.sw-category-detail-products__product-assignment-type-select').typeSingleSelect(
            'Dynamic product group',
            '.sw-category-detail-products__product-assignment-type-select'
        );

        // Verify that the preview shows an empty state first
        cy.get('.sw-product-stream-grid-preview .sw-empty-state__title')
            .should('contain', 'No dynamic product group selected');

        // Select product stream
        cy.get('.sw-category-detail-products__product-stream-select').typeSingleSelect(
            '2nd Product stream',
            '.sw-category-detail-products__product-stream-select'
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
        cy.get('.sw-category-detail-products__product-assignment-type-select .sw-single-select__selection-text')
            .should('contain', 'Dynamic product group');

        cy.get('.sw-category-detail-products__product-stream-select .sw-entity-single-select__selection-text')
            .should('contain', '2nd Product stream');
    });

    it('@catalogue: switch to other category without saving and applied changes', () => {
        cy.createCategoryFixture({
            parent: {
                name: 'ParentCategory',
                active: true
            }
        }).then(() => {
            cy.reload();

            // Select root category
            cy.get('.sw-tree-item__label')
                .contains('Home')
                .click();

            // Modify the category name
            cy.get('#categoryName').clearTypeAndCheck('New Home');

            // Select a different category without saving
            cy.get('.sw-tree-item__label')
                .contains('ParentCategory')
                .click();

            // The change warning modal should be visible
            cy.get('.sw-modal').should('be.visible');
            cy.get('.sw-modal .sw-discard-changes-modal-delete-text').should('be.visible');

            // Select keep editing to stay on current category
            cy.get('.sw-modal .sw-modal__footer .sw-button')
                .contains('Keep editing')
                .click();

            // Verify the modified category name is still present
            cy.get('#categoryName').should('have.value', 'New Home');
        });
    });

    it('@catalogue: saving the data when changing content language', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveData');

        const page = new CategoryPageObject();

        // Select a category
        cy.get('.sw-tree-item__label')
            .contains('Home')
            .click();

        cy.get('#categoryName').clearTypeAndCheck('Home - English');

        page.changeTranslation('Deutsch', 0);
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('#sw-language-switch-save-changes-button').click();

        // Wait for category request with correct data to be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get('.sw-tree-item__label')
                .should('be.visible')
                .contains('Home');
        });

        page.changeTranslation('English', 1);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home - English');
    })
});
