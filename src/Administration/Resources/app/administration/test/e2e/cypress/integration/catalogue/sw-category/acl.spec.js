// / <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Category: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createCategoryFixture({
                    parent: {
                        name: 'ParentCategory',
                        active: true
                    }
                });
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-valid');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@base @catalogue: can view category', () => {
        const page = new CategoryPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        });

        cy.get('.sw-empty-state__title').contains('No category selected');
        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');
        cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

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
            .should('be.visible')
            .should('not.be.disabled');
        cy.get('.sw-category-entry-point-modal__show-in-main-navigation input')
            .scrollIntoView()
            .should('be.visible')
            .should('be.disabled');
        cy.get('#sw-field--selectedSalesChannel-homeName')
            .scrollIntoView()
            .should('be.visible')
            .should('be.disabled');
        cy.get('.sw-category-detail-layout__change-layout-action')
            .scrollIntoView()
            .should('be.visible')
            .should('be.disabled');
        cy.get('#sw-field--selectedSalesChannel-homeMetaTitle')
            .scrollIntoView()
            .should('be.visible')
            .should('be.disabled');
        cy.get('#sw-field--selectedSalesChannel-homeMetaDescription')
            .scrollIntoView()
            .should('be.visible')
            .should('be.disabled');
        cy.get('#sw-field--selectedSalesChannel-homeKeywords')
            .scrollIntoView()
            .should('be.visible')
            .should('be.disabled');

        // close modal
        cy.get('.sw-category-entry-point-modal__cancel-button').click();

        // check cms tab page
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().should('be.visible').click();
        cy.get('.sw-category-detail-layout__change-layout-action').should('be.disabled');
        cy.get('.sw-cms-page-form').should('not.exist');
    });

    it('@catalogue: can edit category', () => {
        const page = new CategoryPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'category',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        });

        cy.get('.sw-empty-state__title').contains('No category selected');
        cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveData');

        // Select a category
        cy.get('.sw-tree-item__label')
            .contains('Home')
            .click();

        // Edit the category
        cy.get('#categoryName').clearTypeAndCheck('Shop');

        // Check if content tab works
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action').should('not.be.disabled');
        cy.get('#sw-field--element-config-minHeight-value').should('have.value', '320px');

        // Save the category
        cy.get('.sw-category-detail__save-action').click();

        // Wait for category request with correct data to be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            expect(xhr.requestBody).to.have.property('name', 'Shop');
        });
    });

    it('@catalogue: can create category', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'category',
                role: 'editor'
            },
            {
                key: 'category',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        });

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
        cy.get('.sw-category-tree__inner .sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });
        cy.get(`${page.elements.categoryTreeItemInner}:nth-child(2)`).contains('Categorian');
    });

    it('@catalogue: can delete category', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'category',
                role: 'editor'
            },
            {
                key: 'category',
                role: 'creator'
            },
            {
                key: 'category',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        });

        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'delete'
        }).as('deleteData');

        const page = new CategoryPageObject();

        cy.clickContextMenuItem(
            '.sw-context-menu__group-button-delete',
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(2)`
        );

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw_tree__confirm-delete-text')
            .contains('ParentCategory');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        // Verify deletion
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
