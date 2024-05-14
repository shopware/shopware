/**
 * @package content
 */
// / <reference types="Cypress" />

import CategoryPageObject from '../../../../support/pages/module/sw-category.page-object';

describe('Category: Test ACL privileges', () => {
    beforeEach(() => {
        cy.createProductFixture()
            .then(() => {
                return cy.createCategoryFixture({
                    parent: {
                        name: 'ParentCategory',
                        active: true,
                    },
                });
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-valid');
            }).then(() => {
                return cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @catalogue: can view category', { tags: ['pa-content-management', 'quarantined', 'VUE3'] }, () => {
        const page = new CategoryPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.contains('.sw-empty-state__title', 'No category selected');
        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');
        cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-category-detail-base').should('be.visible');
        cy.get('#categoryName').should('have.value', 'Home');

        // 'home' category should be have 'main navigation' as entry point, so there are more things
        // that should be visible not disabled
        cy.get('.sw-category-entry-point-card__entry-point-selection')
            .should('be.visible')
            .should('have.class', 'is--disabled');
        cy.get('.sw-category-entry-point-card__sales-channel-selection')
            .should('be.visible')
            .should('have.class', 'is--disabled');

        // open configure home modal
        cy.get('.sw-category-entry-point-card__button-configure-home').click();
        // sales channel switch should still work (to view different configurations)
        cy.get('.sw-category-entry-point-modal__sales-channel-selection')
            .should('not.be.disabled');
        cy.get('.sw-category-entry-point-modal__show-in-main-navigation input')
            .should('be.disabled');
        cy.get('.sw-category-entry-point-modal__name-in-main-navigation input')
            .scrollIntoView()
            .should('be.disabled');
        cy.get('.sw-category-detail-layout__change-layout-action')
            .scrollIntoView()
            .should('be.disabled');
        cy.get('.sw-category-entry-point-modal__meta-title input')
            .scrollIntoView()
            .should('be.disabled');
        cy.get('.sw-category-entry-point-modal__meta-description textarea')
            .scrollIntoView()
            .should('be.disabled');
        cy.get('.sw-category-entry-point-modal__seo-keywords input')
            .scrollIntoView()
            .should('be.disabled');

        // close modal
        cy.get('.sw-category-entry-point-modal__cancel-button').click();

        // check cms tab page
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-detail-layout__change-layout-action').should('be.disabled');
        cy.get('.sw-cms-page-form').should('not.exist');
    });

    it('@catalogue: can edit category', { tags: ['pa-content-management', 'VUE3'] }, () => {
        const page = new CategoryPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'category',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.contains('.sw-empty-state__title', 'No category selected');
        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');

        cy.intercept({
            method: 'PATCH',
            url: `${Cypress.env('apiPath')}/category/*`,
        }).as('saveData');

        // Select a category
        cy.contains('.sw-tree-item__label', 'Home')
            .click();

        // Edit the category
        cy.get('#categoryName').clearTypeAndCheck('Shop');

        // Check if content tab works
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action').should('not.be.disabled');
        cy.get('input[label="Minimum height"]').should('have.value', '320px');

        // Save the category
        cy.get('.sw-category-detail__save-action').click();

        // Wait for category request with correct data to be successful
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
    });

    it('@catalogue: can create category', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'category',
                role: 'editor',
            },
            {
                key: 'category',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        });

        const page = new CategoryPageObject();

        // Request we want to wait for later
        cy.intercept({
            method: 'POST',
            url: `${Cypress.env('apiPath')}/category`,
        }).as('saveData');

        // Add category before root one
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__before-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`,
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('Categorian');
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('{enter}');

        // Verify category
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get(page.elements.categoryTreeItemInner).should('contain', 'Categorian');
    });

    it('@catalogue: can delete category', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'category',
                role: 'editor',
            },
            {
                key: 'category',
                role: 'creator',
            },
            {
                key: 'category',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        });

        cy.intercept({
            method: 'delete',
            url: `${Cypress.env('apiPath')}/category/*`,
        }).as('deleteData');

        const page = new CategoryPageObject();

        cy.contains('.sw-tree-item__element', 'ParentCategory')
            .find(page.elements.contextMenuButton)
            .click({ scrollBehavior: 'top' });
        cy.get('.sw-context-menu__group-button-delete')
            .click({ scrollBehavior: 'top' });
        cy.get('.sw-context-menu__group-button-delete').should('not.exist');

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw_tree__confirm-delete-text', 'ParentCategory');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        // Verify deletion
        cy.wait('@deleteData')
            .its('response.statusCode').should('equal', 204);
    });
});
