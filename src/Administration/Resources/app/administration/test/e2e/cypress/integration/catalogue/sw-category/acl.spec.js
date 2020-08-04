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
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

            const page = new CategoryPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'category',
                    role: 'viewer'
                }
            ]);

            cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
            cy.get('.sw-admin-menu__navigation-list-item.sw-category').click();

            cy.get('.sw-empty-state__title').contains('No category selected');
            cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');
            cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

            cy.get('#categoryName').should('have.value', 'Home');

            cy.get('.sw-category-detail__tab-cms').click();
            cy.get('#sw-field--element-config-minHeight-value').should('have.value', '320px');
        });
    });

    it('@catalogue: can edit category', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

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
            ]);

            cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
            cy.get('.sw-admin-menu__navigation-list-item.sw-category').click();

            cy.get('.sw-empty-state__title').contains('No category selected');
            cy.get(`${page.elements.categoryTreeItem}__icon`).should('be.visible');

            cy.server();
            cy.route({
                url: '/api/v*/category/*',
                method: 'patch'
            }).as('saveData');

            // Select a category
            cy.get('.sw-tree-item__label')
                .contains('Home')
                .click();

            // Edit the category
            cy.get('#categoryName').clearTypeAndCheck('Shop');

            // Save the category
            cy.get('.sw-category-detail__save-action').click();

            // Wait for category request with correct data to be successful
            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
                expect(xhr.requestBody).to.have.property('name', 'Shop');
            });
        });
    });

    it('@catalogue: can create category', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

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
            ]);

            cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
            cy.get('.sw-admin-menu__navigation-list-item.sw-category').click();

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
                `${page.elements.categoryTreeItem}:nth-of-type(1)`
            );
            cy.get(`${page.elements.categoryTreeItem}__content input`).type('Categorian');
            cy.get(`${page.elements.categoryTreeItem}__content input`).type('{enter}');

            // Verify category
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
            cy.get(`${page.elements.categoryTreeItem}:nth-child(2)`).contains('Categorian');
        });
    });

    it('@catalogue: can delete category', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

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
            ]);

            cy.route({
                url: `${Cypress.env('apiPath')}/category/*`,
                method: 'delete'
            }).as('deleteData');

            cy.get('.sw-admin-menu__navigation-list-item.sw-catalogue').click();
            cy.get('.sw-admin-menu__navigation-list-item.sw-category').click();

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
});
