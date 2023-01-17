// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../../support/pages/module/sw-sales-channel.page-object';
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Sales Channel: Test product comparison', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('product-stream', {}, 'product-stream-active');
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@general: create product comparison sales channel', {  browser: '!firefox', tags: ['pa-sales-channels'] }, () => {
        const page = new SalesChannelPageObject();
        const productPage = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/sales-channel`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProductsData');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/product-export/validate`,
            method: 'POST',
        }).as('validate');

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Upload product image
        cy.get('input[name=sw-field--product-name]').should('be.visible');
        cy.get('.sw-product-media-form .sw-media-upload-v2__file-input')
            .attachFile('img/sw-login-background.png');
        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('input[name=sw-field--product-name]').should('be.visible');
        cy.get(productPage.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveProductsData').its('response.statusCode').should('equal', 200);

        // Open sales channel creation
        cy.contains('.sw-admin-menu__headline', 'Sales Channel');

        cy.get('.sw-admin-menu__headline-action').click();
        cy.contains('.sw-sales-channel-modal .sw-modal__title', 'Add Sales Channel');
        cy.contains(`${page.elements.gridRow}--1 .sw-sales-channel-modal-grid__item-name`,
            'Product comparison');
        cy.get(`${page.elements.gridRow}--1 .sw-sales-channel-modal-grid__item-name`).click();
        cy.contains('.sw-sales-channel-modal .sw-modal__title', 'Product comparison - details');
        cy.get('.sw-sales-channel-modal__add-sales-channel-action').click();

        // Fill in form and save new sales channel
        cy.get('#sw-field--templateName').select('Google Shopping (XML)');

        cy.get('.sw-modal').should('be.visible');
        cy.contains('.sw-modal__body',
            'If you apply the template, existing data in this Sales Channel will be overwritten');
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.awaitAndCheckNotification('Template data has been applied, but not yet saved. Please save this Sales Channel to keep the changes.');

        cy.get(page.elements.salesChannelNameInput).typeAndCheck('A great Product comparison');
        cy.get('.sw-sales-channel-detail__tax-calculation').scrollIntoView();
        cy.get('#sw-field--salesChannel-taxCalculationType-0').click();

        cy.contains('.sw-card__title', 'Storefront Sales Channel').scrollIntoView();
        cy.get('.sw-sales-channel-detail__product-comparison-storefront')
            .typeSingleSelectAndCheck('Storefront', '.sw-sales-channel-detail__product-comparison-storefront');
        cy.get('.sw-sales-channel-detail__product-comparison-domain')
            .typeSingleSelectAndCheck(Cypress.config('baseUrl'), '.sw-sales-channel-detail__product-comparison-domain');

        cy.get('.sw-sales-channel-detail__product-comparison-product-stream').scrollIntoView();
        cy.get('.sw-sales-channel-detail__product-comparison-product-stream')
            .typeSingleSelectAndCheck('2nd Product stream', '.sw-sales-channel-detail__product-comparison-product-stream');

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify creation
        cy.get(page.elements.salesChannelNameInput).should('have.value', 'A great Product comparison');
        cy.get('a[title="Template"]').should('be.visible');
        cy.get('a[title="Template"]').click();
        cy.contains('.sw-card__title', 'Template');

        cy.get('.sw-sales-channel-detail-product-comparison__test-action').scrollIntoView();
        cy.get('.sw-sales-channel-detail-product-comparison__test-action').should('be.visible');
        cy.get('.sw-sales-channel-detail-product-comparison__test-action').click();

        cy.wait('@validate').its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('No errors occurred.');
    });
});

