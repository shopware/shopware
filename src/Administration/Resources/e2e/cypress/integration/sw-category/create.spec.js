/// <reference types="Cypress" />

import CategoryPageObject from '../../support/pages/module/sw-category.page-object';

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

    it('create a category after root category', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/category?_response=true',
            method: 'post'
        }).as('saveData');

        // Add category after root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__after-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData').then(() => {
            cy.get('.sw-confirm-field__button-list').then((btn) => {
                if (btn.attr('style').includes('display: none;')) {
                    cy.get('.sw-tree-actions__headline').click();
                } else {
                    cy.get('.sw-confirm-field__button--cancel').click();
                }
            });
            cy.get(`${page.elements.categoryTreeItem}:nth-child(2)`).contains('Categorian');
        });
    });

    it('create a category before root category', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/category?_response=true',
            method: 'post'
        }).as('saveData');

        // Add category before root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__before-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData').then(() => {
            cy.get('.sw-confirm-field__button-list').then((btn) => {
                if (btn.attr('style').includes('display: none;')) {
                    cy.get('.sw-tree-actions__headline').click();
                } else {
                    cy.get('.sw-confirm-field__button--cancel').click();
                }
            });
            cy.get(`${page.elements.categoryTreeItem}:nth-child(1)`).contains('Categorian');
        });
    });

    it('@p create a subcategory', () => {
        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/category?_response=true',
            method: 'post'
        }).as('saveData');

        // Add category before root one
        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__sub-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData').then(() => {
            cy.get('.sw-confirm-field__button-list').then((btn) => {
                if (btn.attr('style').includes('display: none;')) {
                    cy.get('.sw-tree-actions__headline').click();
                } else {
                    cy.get('.sw-confirm-field__button--cancel').click();
                }
            });
            cy.get(`${page.elements.categoryTreeItem}:nth-child(1)`).contains('Categorian');
        });
    });
});
