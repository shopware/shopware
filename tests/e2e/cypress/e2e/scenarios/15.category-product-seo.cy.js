/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import CategoryPageObject from '../../support/pages/module/sw-category.page-object';

describe('Category: Assign product and set seo url, then check in the storefront', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: test seo url for the category ', { tags: ['pa-sales-channels', 'VUE3'] }, () => {
        const page = new CategoryPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/category`,
            method: 'POST',
        }).as('saveCategory');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/category/**`,
            method: 'PATCH',
        }).as('editCategory');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('addProductToSaleschannel');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('loadSalesChannel');

        // Add sub category under home
        cy.get(`${page.elements.categoryTreeItemInner}__icon`).should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.categoryTreeItem}__sub-action`,
            page.elements.contextMenuButton,
            `${page.elements.categoryTreeItemInner}:nth-of-type(1)`,
        );
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('SEO-Category');
        cy.get(`${page.elements.categoryTreeItemInner}__content input`).then(($btn) => {
            if ($btn) {
                cy.get(`${page.elements.categoryTreeItemInner}__content input`).should('be.visible');
                cy.get(`${page.elements.categoryTreeItemInner}__content input`).type('{enter}');
            }
        });

        // Save and verify category
        cy.wait('@saveCategory').its('response.statusCode').should('equal', 204);
        cy.get('.sw-confirm-field__button-list').then((btn) => {
            if (btn.attr('style').includes('display: none;')) {
                cy.get('.sw-category-tree__inner .sw-tree-actions__headline').click();
            } else {
                cy.get('.sw-category-tree__inner .sw-confirm-field__button--cancel').click();
            }
        });
        cy.contains(`${page.elements.categoryTreeItemInner}:nth-child(1)`, 'SEO-Category');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');
        cy.contains('SEO-Category').click();

        // Activate and assign product to the category
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);
        cy.get('.sw-category-detail-base').should('be.visible');
        cy.get('input[name="categoryActive"]').click();
        cy.get('.sw-tabs-item[title="Producten"]').scrollIntoView().click();
        cy.get('input[placeholder="Producten zoeken en toewijzen …"]').click();
        cy.contains('.sw-select-result-list__content', 'Product name').click();
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@editCategory').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Add SEO url to the category
        cy.get('[title="SEO"]').scrollIntoView().click();
        cy.get('[label] .sw-entity-single-select__selection').scrollIntoView().click();
        cy.get('.sw-select-result-list__content').contains(Cypress.env('storefrontName')).click();
        cy.get('.sw-inherit-wrapper [type]').clearTypeAndCheck('test-SEO');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategory');
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@loadCategory').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Add product to sales channel
        cy.contains(Cypress.env('storefrontName')).click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@addProductToSaleschannel').its('response.statusCode').should('equal', 204);
        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@loadSalesChannel').its('response.statusCode').should('equal', 200);

        // Verify category in the storefront
        cy.visit('/');
        cy.contains('SEO-Category').click();
        cy.url().should('include', 'test-SEO');
        cy.get('.main-navigation-link.active').should('be.visible');
        cy.contains('.breadcrumb-title', 'SEO-Category').should('be.visible');
        cy.contains('.cms-element-product-listing', 'Product name').should('be.visible');
    });
});
