// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';
import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Media: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@visual: check appearance of basic media workflow', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST'
        }).as('saveDataFileUpload');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/media`,
            method: 'POST'
        }).as('getData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'POST'
        }).as('saveDataUrlUpload');

        cy.clickMainMenuItem({
            targetPath: '#/sw/media/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-media'
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-media-index__page-content').should('be.visible');

        if (Cypress.isBrowser({ family: 'chromium' })) {
            page.uploadImageUsingFileUpload('img/sw-login-background.png');

            cy.wait('@saveDataFileUpload')
                .its('response.statusCode').should('equal', 204);
            cy.awaitAndCheckNotification('File has been saved.');
            cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
                .should('be.visible');
        }

        if (Cypress.isBrowser('firefox')) {
            // Upload medium
            cy.clickContextMenuItem(
                '.sw-media-upload-v2__button-url-upload',
                '.sw-media-upload-v2__button-context-menu'
            );
            page.uploadImageUsingUrl('http://assets.shopware.com/sw_logo_white.png');

            cy.wait('@saveDataUrlUpload')
                .its('response.statusCode').should('equal', 204);
            cy.awaitAndCheckNotification('File has been saved.');
            cy.get('.sw-media-base-item__name[title="sw_logo_white.png"]')
                .should('be.visible');
        }

        // Take snapshot for visual testing
        cy.takeSnapshot('[Media] Listing', '.sw-media-library');
    });

    it('@visual: check appearance of basic product media workflow', () => {
        const page = new ProductPageObject();

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');

        // Open product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Add first image to product
        cy.get('.sw-product-media-form__previews').scrollIntoView();
        cy.get('#files')
            .attachFile('img/sw-login-background.png');
        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.awaitAndCheckNotification('File has been saved.');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Product] Detail,  with image', '.sw-product-image__image');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct')
            .its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-name').click();
        cy.get('.gallery-slider-single-image > .img-fluid').should('be.visible');
        cy.get('.gallery-slider-single-image > .img-fluid')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);

        // Take snapshot for visual testing
        cy.takeSnapshot('[Product] Storefront, with image', '.gallery-slider-single-image > .img-fluid');
    });
});
