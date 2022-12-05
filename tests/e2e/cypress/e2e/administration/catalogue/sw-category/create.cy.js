/**
 * @package content
 */
// / <reference types="Cypress" />

import CategoryPageObject from '../../../../support/pages/module/sw-category.page-object';

describe('Category: Create several categories', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@catalogue: create a category after root category', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'POST'
        }).as('saveData');

        // Add category after root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__after-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
            cy.contains(`${page.elements.categoryTreeItemInner}:nth-child(2)`, 'Categorian');
        });
    });

    it('@catalogue: create a category before root category', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'POST'
        }).as('saveData');

        // Add category before root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__before-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });
        cy.contains(`${page.elements.categoryTreeItemInner}:nth-child(1)`, 'Categorian');
    });

    it('@catalogue: delete additional element after create category', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'POST'
        }).as('saveData');

        // Add category after root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__after-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('Categorian{enter}');

        // Verify category
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
            cy.get(`${page.elements.categoryTreeItemInner}`).should('have.length', 2);
        });
    });

    it('@base @catalogue @package: create a subcategory', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST'
        }).as('loadCategory');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/**`,
            method: 'PATCH'
        }).as('editCategory');

        // Add category before root one
        cy.get(`${page.elements.categoryTreeItemInner}__icon`).should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__sub-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).then(($btn) => {
            if ($btn) {
                cy.get(`${page.elements.categoryTreeItemInner}__content input`).should('be.visible');
                cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('{enter}');
            }
        });

        // Save and verify category
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });
        cy.contains(`${page.elements.categoryTreeItemInner}:nth-child(1)`, 'Categorian');
        cy.contains('Categorian').click();

        // Assign category and set it active
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);
        cy.get('.sw-category-detail-base').should('be.visible');
        cy.get('input[name="categoryActive"]').click();
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@editCategory').its('response.statusCode').should('equal', 204);

        // Verify category in Storefront
        cy.visit('/');
        cy.contains('Categorian').click();
        cy.get('.main-navigation-link.active').should('be.visible');
    });

    it('@catalogue: create a category with layout default', { tags: ['pa-content-management'] }, () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'POST'
        }).as('saveData');

        // Add category after root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__after-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });

        cy.contains('.sw-tree-item__label', 'Categorian').click();

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.contains('.sw-category-layout-card__desc-headline', 'Default listing layout');
        cy.contains('.sw-category-layout-card__desc-subheadline', 'Listing page');
    });
});
