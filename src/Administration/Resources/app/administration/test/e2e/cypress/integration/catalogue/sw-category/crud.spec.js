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
            cy.get('.sw-loader').should('not.exist');
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
            cy.get('.sw-category-detail__save-action')
                .should('have.css', 'background-color', 'rgb(24, 158, 255)');
        });

        // Verify category in Storefront
        cy.visit('/');
        cy.contains('Categorian').click();
        cy.get('.main-navigation-link.active').should('be.visible');
    });

    it('@base @catalogue: should hide the elements not needed for a Structuring element / Entry point', () => {
        const page = new CategoryPageObject();

        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');
        cy.get('.tree-link').click();

        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-layout-card').should('exist');
        cy.get('.sw-category-detail__tab-base').scrollIntoView().should('be.visible').click();

        cy.get('.sw-category-detail-base__menu').should('exist');
        cy.get('.sw-category-detail__tab-products').scrollIntoView().should('be.visible');

        cy.get('.sw-category-detail__tab-seo').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-seo-form').should('exist');
        cy.get('.sw-seo-url__card').should('exist');
        cy.get('.sw-category-detail__tab-base').scrollIntoView().should('be.visible').click();

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible');
        cy.get('.sw-category-link-settings').should('not.exist');

        // change category type to Structuring element
        cy.get('.sw-category-detail-base__type-selection')
            .typeSingleSelectAndCheck('Entry point', '.sw-category-detail-base__type-selection');

        cy.get('.sw-category-detail__tab-products').scrollIntoView().should('not.be.visible');
        cy.get('.sw-category-detail__tab-seo').scrollIntoView().should('not.be.visible');

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('not.be.visible');
        cy.get('.sw-category-link-settings').should('not.exist');

        cy.get('.sw-category-detail-base__menu').should('exist');

        // change category type back to Category
        cy.get('.sw-category-detail-base__type-selection')
            .typeSingleSelectAndCheck('Page / List', '.sw-category-detail-base__type-selection');

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-layout-card').should('exist');
        cy.get('.sw-category-detail__tab-base').scrollIntoView().should('be.visible').click();

        cy.get('.sw-category-detail-base__menu').scrollIntoView().should('be.visible');
        cy.get('.sw-category-detail__tab-products').scrollIntoView().should('be.visible');

        cy.get('.sw-category-detail__tab-seo').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-seo-form').should('exist');
        cy.get('.sw-seo-url__card').should('exist');
        cy.get('.sw-category-detail__tab-base').scrollIntoView().should('be.visible').click();

        cy.get('.sw-category-link-settings').should('not.exist');

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible');
    });

    it('@base @catalogue: should hide the elements not needed for a Link', () => {
        const page = new CategoryPageObject();

        // we need to create a new category, because Home can not be a link because it's an entry point
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
            cy.get('.sw-loader').should('not.exist');
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

        cy.get('.sw-category-detail-base__menu').should('exist');
        cy.get('.sw-category-detail__tab-products').scrollIntoView().should('be.visible');

        cy.get('.sw-category-detail__tab-seo').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-seo-form').should('exist');
        cy.get('.sw-seo-url__card').should('exist');
        cy.get('.sw-category-detail__tab-base').should('be.visible').click();

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-layout-card').should('exist');
        cy.get('.sw-category-detail__tab-base').should('be.visible').click();

        cy.get('.sw-category-link-settings').should('not.exist');

        // change category type to Customisable link
        cy.get('.sw-category-detail-base__type-selection')
            .typeSingleSelectAndCheck('Link', '.sw-category-detail-base__type-selection');


        cy.get('.sw-category-detail__tab-products').scrollIntoView().should('not.be.visible');
        cy.get('.sw-category-detail__tab-seo').scrollIntoView().should('not.be.visible');

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('not.be.visible');
        cy.get('.sw-category-detail-base__menu').should('not.exist');

        cy.get('.sw-category-link-settings').should('exist');

        // change category type back to Category
        cy.get('.sw-category-detail-base__type-selection')
            .typeSingleSelectAndCheck('Page / List', '.sw-category-detail-base__type-selection');

        cy.get('.sw-category-detail-base__menu').should('exist');
        cy.get('.sw-category-detail__tab-products').scrollIntoView().should('be.visible');
        cy.get('.sw-category-link-settings').should('not.exist');

        cy.get('.sw-category-detail__tab-seo').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-seo-form').should('exist');
        cy.get('.sw-seo-url__card').should('exist');
        cy.get('.sw-category-detail__tab-base').should('be.visible').click();

        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-layout-card').should('exist');
    });
});
