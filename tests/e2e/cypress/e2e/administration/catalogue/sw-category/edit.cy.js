/**
 * @package content
 */
// / <reference types="Cypress" />

import CategoryPageObject from '../../../../support/pages/module/sw-category.page-object';

describe('Category: Edit categories', () => {
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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@catalogue: change content language without selected category', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        cy.contains('.sw-tree-item__label', 'Home')
            .should('be.visible');

        page.changeTranslation('Deutsch', 0);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-empty-state__element').should('be.visible');

        cy.contains('.sw-tree-item__label', 'Home')
            .should('be.visible');

        page.changeTranslation('English', 1);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-empty-state__element').should('be.visible');

        cy.contains('.sw-tree-item__label', 'Home')
            .should('be.visible');
    });

    it('@catalogue: change content language with selected category', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        cy.contains('.sw-tree-item__label', 'Home')
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

    it('@catalogue: assign dynamic product group', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveData');

        // Select a category
        cy.contains('.sw-tree-item__label', 'Home')
            .click();

        // Switch to products tab
        cy.contains('.sw-category-detail__tab-products', 'Products')
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
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Verify configured data is correct after save action
        cy.get('.sw-category-detail-products__product-assignment-type-select .sw-single-select__selection-text')
            .should('contain', 'Dynamic product group');

        cy.get('.sw-category-detail-products__product-stream-select .sw-entity-single-select__selection-text')
            .should('contain', '2nd Product stream');
    });

    it('@catalogue: switch to other category without saving and applied changes', { tags: ['pa-content-management'] }, () => {
        cy.createCategoryFixture({
            parent: {
                name: 'ParentCategory',
                active: true
            }
        }).then(() => {
            cy.reload();

            // Select root category
            cy.contains('.sw-tree-item__label', 'Home')
                .click();

            // Modify the category name
            cy.get('#categoryName').clearTypeAndCheck('New Home');

            // Select a different category without saving
            cy.contains('.sw-tree-item__label', 'ParentCategory')
                .click();

            // The change warning modal should be visible
            cy.get('.sw-modal').should('be.visible');
            cy.get('.sw-modal .sw-discard-changes-modal-delete-text').should('be.visible');

            // Select keep editing to stay on current category
            cy.contains('.sw-modal .sw-modal__footer .sw-button', 'Keep editing')
                .click();

            // Verify the modified category name is still present
            cy.get('#categoryName').should('have.value', 'New Home');
        });
    });

    it('@catalogue: saving the data when changing content language', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveData');

        const page = new CategoryPageObject();

        // Select a category
        cy.contains('.sw-tree-item__label', 'Home')
            .click();

        cy.get('#categoryName').clearTypeAndCheck('Home - English');

        page.changeTranslation('Deutsch', 0);
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('#sw-language-switch-save-changes-button').click();
        cy.get('.sw-modal__dialog').should('not.exist');

        // Wait for category request with correct data to be successful
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.contains('.sw-tree-item__label', 'Home')
            .should('be.visible');

        page.changeTranslation('English', 1);
        cy.contains('.sw-tree-item__label', 'Home - English')
            .should('be.visible');
    });
});
