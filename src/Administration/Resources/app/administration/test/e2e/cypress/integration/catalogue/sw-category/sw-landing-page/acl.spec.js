// / <reference types="Cypress" />

import CategoryPageObject from '../../../../support/pages/module/sw-category.page-object';

describe('Landing pages: Test ACL privileges', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        let salesChannel;
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                });
            })
            .then((data) => {
                salesChannel = data.id;
                return cy.createDefaultFixture('cms-page', {}, 'cms-landing-page');
            })
            .then((data) => {
                cy.createDefaultFixture('landing-page', {
                    cmsPage: data,
                    salesChannels: [
                        {
                            id: salesChannel
                        }
                    ]
                }, 'landing-page');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@catalogue: can duplicate landing pages', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'editor'
            },
            {
                key: 'landing_page',
                role: 'creator'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/category/index`)

        // Request for duplicate landing page
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/clone/landing-page/*`,
            method: 'post'
        }).as('duplicateData');

        // Request for loading landing pages
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');

        // Collapse category and expand landing page tree
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Waiting for loading landing pages
        cy.wait('@loadLandingPage');

        // Click on duplicate in context menu
        const page = new CategoryPageObject();
        cy.clickContextMenuItem(
            '.sw-context-menu__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`
        );

        // Verify duplicate
        cy.wait('@duplicateData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get(`${page.elements.categoryTreeItem}:nth-child(2)`).contains('Testingpage Copy');
        });
    });

    it('@catalogue: can create landing pages', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'editor'
            },
            {
                key: 'landing_page',
                role: 'creator'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/category/index`)

        // Request for save landing page
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/landing-page`,
            method: 'post'
        }).as('saveData');

        // Request for loading the landing pages
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');

        // Collapse category tree and expand landing page tree
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Wait for loading the landing pages
        cy.wait('@loadLandingPage');

        // Click on add landing page button
        cy.get('.sw-landing-page-tree__add-button a').click();

        // Fill in landing page information
        cy.get('#landingPageName').typeAndCheck('MyLandingPage');
        cy.get('input[name="landingPageActive"]').check();
        cy.get('.sw-landing-page-detail-base__sales_channel').typeMultiSelectAndCheck('Storefront');
        cy.get('#sw-field--landingPage-url').typeAndCheck('my-landing-page');

        // Save landing page
        cy.get('.sw-category-detail__save-landing-page-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            const page = new CategoryPageObject();
            cy.get(`${page.elements.categoryTreeItem}:nth-child(2)`).first().contains('MyLandingPage');
        });
    });

    it('@catalogue: can view landing pages', () => {
        const page = new CategoryPageObject();
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'viewer'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/category/index`)

        // Request for loading landing pages
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPages');

        // Collapse category and expand landing page tree
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Loading landing pages
        cy.wait('@loadLandingPages');

        // Expect empty state
        cy.get('.sw-empty-state__title').contains('No category selected');

        // Click on the first landing page to view details
        cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

        // Expect the landing page
        cy.get('#landingPageName').should('have.value', 'Testingpage');
    });

    it('@catalogue: can edit landing pages', () => {
        const page = new CategoryPageObject();
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'editor'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/category/index`)

        cy.server();

        // Request for update landing page
        cy.route({
            url: `${Cypress.env('apiPath')}/landing-page/*`,
            method: 'patch'
        }).as('saveData');

        // Request for loading landing pages
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPages');

        // Collapse category and expand landing page tree
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Loading landing pages
        cy.wait('@loadLandingPages');

        // Expect empty screen
        cy.get('.sw-empty-state__title').contains('No category selected');

        // Open landing page for edit
        cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

        // Select landing page
        cy.get('#landingPageName').should('have.value', 'Testingpage');

        // Edit the landing page
        cy.get('#landingPageName').clearTypeAndCheck('Page');

        // Save the landing page
        cy.get('.sw-category-detail__save-landing-page-action').click();

        // Wait for landing page request with correct data to be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            expect(xhr.requestBody).to.have.property('name', 'Page');
        });
    });

    it('@catalogue: can delete landing pages', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'viewer'
            },
            {
                key: 'landing_page',
                role: 'editor'
            },
            {
                key: 'landing_page',
                role: 'creator'
            },
            {
                key: 'landing_page',
                role: 'deleter'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/category/index`)

        // Request for delete landing page
        cy.route({
            url: `${Cypress.env('apiPath')}/landing-page/*`,
            method: 'delete'
        }).as('deleteData');

        // Request for loading landing pages
        cy.route('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');

        // Collapse category and expand landing page tree
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Loading landing pages
        cy.wait('@loadLandingPage');

        // Click on delete in context menu
        const page = new CategoryPageObject();
        cy.clickContextMenuItem(
            '.sw-context-menu__group-button-delete',
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`
        );

        // Expect delete modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw_tree__confirm-delete-text')
            .contains('Testingpage');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        // Verify deletion
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
