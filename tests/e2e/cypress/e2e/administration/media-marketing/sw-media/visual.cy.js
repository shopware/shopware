/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Media: Visual tests', { tags: ['VUE3'] }, () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@visual: check appearance of basic media workflow', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST',
        }).as('saveDataFileUpload');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=plugin-manager--login`,
            method: 'POST',
        }).as('saveDataUrlUpload');

        cy.setEntitySearchable('media', ['fileName', 'title']);

        cy.setEntitySearchable('media', ['fileName', 'title']);

        cy.get('sw-skeleton').should('not.exist');
        cy.clickMainMenuItem({
            targetPath: '#/sw/media/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-media',
        });
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-media-index__page-content').should('be.visible');

        if (Cypress.isBrowser({ family: 'chromium' })) {
            page.uploadImageUsingFileUpload('img/sw-login-background.png');

            cy.wait('@saveDataFileUpload')
                .its('response.statusCode').should('equal', 204);
            cy.awaitAndCheckNotification('File has been saved.');
            cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
                .should('be.visible');
            cy.get('.sw-media-preview-v2__item').should('have.attr', 'src');
            cy.get('.icon--multicolor-file-thumbnail-broken').should('not.exist');
            cy.get('.icon--multicolor-file-thumbnail-normal').should('not.exist');
        }

        if (Cypress.isBrowser('firefox')) {
            // Upload medium
            cy.clickContextMenuItem(
                '.sw-media-upload-v2__button-url-upload',
                '.sw-media-upload-v2__button-context-menu',
            );
            page.uploadImageUsingUrl(`${Cypress.config('baseUrl')}/bundles/administration/static/img/plugin-manager--login.png`);

            cy.wait('@saveDataUrlUpload')
                .its('response.statusCode').should('equal', 204);
            cy.awaitAndCheckNotification('File has been saved.');
            cy.get('.sw-media-base-item__name[title="plugin-manager--login.png"]')
                .should('be.visible');
            cy.get('.sw-media-preview-v2__item').should('have.attr', 'src');
            cy.get('.icon--multicolor-file-thumbnail-broken').should('not.exist');
            cy.get('.icon--multicolor-file-thumbnail-normal').should('not.exist');
        }

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Media] Listing', '.sw-media-library', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });

    it('@visual: check appearance of basic product media workflow', { tags: ['pa-content-management'] }, () => {
        const page = new ProductPageObject();

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveProduct');

        // Open product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Add first image to product
        cy.get('.sw-product-media-form__previews').scrollIntoView();
        cy.get('.sw-product-media-form .sw-media-upload-v2__file-input')
            .attachFile('img/sw-login-background.png');
        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.awaitAndCheckNotification('File has been saved.');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Product] Detail,  with image', '.sw-product-image__image', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

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
