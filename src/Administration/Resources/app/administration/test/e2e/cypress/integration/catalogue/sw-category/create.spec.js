// / <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Category: Create several categories', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@catalogue: create a category after root category', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'post'
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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
            cy.get(`${page.elements.categoryTreeItemInner}:nth-child(2)`).contains('Categorian');
        });
    });

    it('@catalogue: create a category before root category', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'post'
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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });
        cy.get(`${page.elements.categoryTreeItemInner}:nth-child(1)`).contains('Categorian');
    });

    it('@base @catalogue: create a subcategory', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'post'
        }).as('loadCategory');
        cy.route({
            url: `${Cypress.env('apiPath')}/category/**`,
            method: 'patch'
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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });
        cy.get(`${page.elements.categoryTreeItemInner}:nth-child(1)`).contains('Categorian');
        cy.contains('Categorian').click();

        // Assign category and set it active
        cy.wait('@loadCategory').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-category-detail-base').should('be.visible');
        cy.get('input[name="categoryActive"]').click();
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@editCategory').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify category in Storefront
        cy.visit('/');
        cy.contains('Categorian').click();
        cy.get('.main-navigation-link.active').should('be.visible');
    });

    it('@catalogue: create a category with layout default', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category`,
            method: 'post'
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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });

        cy.get('.sw-tree-item__label')
            .contains('Categorian')
            .click();

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-layout-card__desc-headline').contains('Default category layout');
        cy.get('.sw-category-layout-card__desc-subheadline').contains('Listing page');
    });
});
