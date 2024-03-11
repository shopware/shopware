/**
 * @package content
 */
// / <reference types="Cypress" />

import CategoryPageObject from '../../../../../support/pages/module/sw-category.page-object';

describe('Landing pages: Test ACL privileges', () => {
    beforeEach(() => {
        cy.log('Clean previous state and prepare Administration');
        let salesChannel;
        cy.searchViaAdminApi({
            endpoint: 'sales-channel',
            data: {
                field: 'name',
                type: 'equals',
                value: 'Storefront',
            },
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
                            id: salesChannel,
                        },
                    ],
                }, 'landing-page');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@catalogue: can duplicate landing pages', {tags: ['pa-content-management', 'VUE3']}, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'editor',
            },
            {
                key: 'landing_page',
                role: 'creator',
            },
        ]);

        cy.log('Request for duplicate landing page');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/landing-page/*`,
            method: 'POST',
        }).as('duplicateData');

        cy.log('Request for loading landing pages');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');

        cy.log('Collapse category and expand landing page tree');
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        cy.log('Waiting for loading landing pages');
        cy.wait('@loadLandingPage');

        cy.log('Click on duplicate in context menu');
        const page = new CategoryPageObject();
        cy.clickContextMenuItem(
            '.sw-context-menu__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`,
        );

        cy.log('Verify duplicate');
        cy.wait('@duplicateData')
            .its('response.statusCode').should('equal', 200);
        cy.contains(`${page.elements.categoryTreeItem}:nth-child(2)`, 'Testingpage Copy');
    });

    it('@catalogue: can create landing pages', {tags: ['pa-content-management', 'VUE3']}, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'editor',
            },
            {
                key: 'landing_page',
                role: 'creator',
            },
        ]);

        cy.log('Request for save landing page');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/landing-page`,
            method: 'POST',
        }).as('saveData');

        cy.log('Request for loading the landing pages');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');

        cy.log('Collapse category tree and expand landing page tree');
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        cy.log('Wait for loading the landing pages');
        cy.wait('@loadLandingPage');

        cy.log('Click on add landing page button');
        cy.get('.sw-landing-page-tree__add-button a').click();

        cy.log('Fill in landing page information');
        cy.get('input[name="landingPageName"]').typeAndCheck('MyLandingPage');
        cy.get('input[name="landingPageActive"]').check();
        cy.get('.sw-landing-page-detail-base__sales_channel').typeMultiSelectAndCheck('Storefront');
        cy.get('input[name="landingPageUrl"]').typeAndCheck('my-landing-page');

        cy.log('Save landing page');
        cy.get('.sw-category-detail__save-landing-page-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        const page = new CategoryPageObject();
        cy.get(`${page.elements.categoryTreeItem}:nth-child(2)`).first().contains('MyLandingPage');
    });

    it('@catalogue: can view landing pages', {tags: ['pa-content-management', 'VUE3']}, () => {
        const page = new CategoryPageObject();
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'viewer',
            },
        ]);

        cy.log('Request for loading landing pages');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPages');

        cy.log('Collapse category and expand landing page tree');
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        cy.log('Loading landing pages');
        cy.wait('@loadLandingPages');

        cy.log('Expect empty state');
        cy.contains('.sw-empty-state__title', 'No category selected');

        cy.log('Click on the first landing page to view details');
        cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

        cy.log('Expect the landing page');
        cy.get('#landingPageName').should('have.value', 'Testingpage');
    });

    it('@catalogue: can edit landing pages', {tags: ['pa-content-management', 'VUE3']}, () => {
        const page = new CategoryPageObject();
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'editor',
            },
        ]);

        cy.log('Request for update landing page');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/landing-page/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.log('Request for loading landing pages');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPages');

        cy.log('Collapse category and expand landing page tree');
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        cy.log('Loading landing pages');
        cy.wait('@loadLandingPages');

        cy.log('Expect empty screen');
        cy.contains('.sw-empty-state__title', 'No category selected');

        cy.log('Open landing page for edit');
        cy.get(`${page.elements.categoryTreeItem}__content`).first().click();

        cy.log('Select landing page');
        cy.get('#landingPageName').should('have.value', 'Testingpage');

        cy.log('Edit the landing page');
        cy.get('#landingPageName').clearTypeAndCheck('Page');

        cy.log('Save the landing page');
        cy.get('.sw-category-detail__save-landing-page-action').click();

        cy.log('Wait for landing page request with correct data to be successful');
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
    });

    it('@catalogue: can delete landing pages', {tags: ['pa-content-management', 'VUE3']}, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'category',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'viewer',
            },
            {
                key: 'landing_page',
                role: 'editor',
            },
            {
                key: 'landing_page',
                role: 'creator',
            },
            {
                key: 'landing_page',
                role: 'deleter',
            },
        ]);

        cy.log('Request for delete landing page');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/landing-page/*`,
            method: 'delete',
        }).as('deleteData');

        cy.log('Request for loading landing pages');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/landing-page`).as('loadLandingPage');

        cy.log('Collapse category and expand landing page tree');
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        cy.log('Loading landing pages');
        cy.wait('@loadLandingPage');

        cy.log('Click on delete in context menu');
        const page = new CategoryPageObject();
        cy.clickContextMenuItem(
            '.sw-context-menu__group-button-delete',
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItem}:nth-of-type(1)`,
        );

        cy.log('Expect delete modal to be open');
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw_tree__confirm-delete-text', 'Testingpage');

        cy.get('.sw-modal__footer > .sw-button--danger > .sw-button__content')
            .should('not.be.disabled')
            .click();

        cy.log('Verify deletion');
        cy.wait('@deleteData')
            .its('response.statusCode').should('equal', 204);
    });
});
