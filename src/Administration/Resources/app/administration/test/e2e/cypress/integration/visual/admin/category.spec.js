/// <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Category: Visual tests', () => {
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

    it('@visual: check appearance of basic category workflow', () => {
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
        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__sub-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`
        );
        cy.get(`${page.elements.categoryTreeItem}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItem}__content input`).then(($btn) => {
            if ($btn) {
                cy.get(`${page.elements.categoryTreeItem}__content input`).should('be.visible');
                cy.get(`${page.elements.categoryTreeItem}__content input`).type('{enter}');
            }
        });

        // Save and verify category
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-confirm-field__button--cancel').click();
            }
        });
        cy.get(`${page.elements.categoryTreeItem}:nth-child(1)`).contains('Categorian');
        cy.contains('Categorian').click();

        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail__save-action')
            .should('have.css', 'background-color', 'rgb(24, 158, 255)');

        cy.takeSnapshot('Category - detail', '.sw-category-detail-base');
    });
});
