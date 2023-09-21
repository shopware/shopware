/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import MediaPageObject from '../../support/pages/module/sw-media.page-object';

describe('CMS: product page layout', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Page Product',
            productNumber: 'PP-123',
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    const page = new ProductPageObject();
    const pageMedia = new MediaPageObject();

    it('@package: create product page layout with image', { tags: ['pa-content-management', 'quarantined'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/cms-page`,
            method: 'POST',
        }).as('saveLayout');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('editLayout');

        // Create
        cy.contains('Nieuwe lay-out aanmaken').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Product page').click();
        cy.contains('.sw-cms-create-wizard__title', 'Kies een sectietype om te beginnen.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Volledige breedte').click();
        cy.contains('.sw-cms-create-wizard__title', 'Hoe moet de nieuwe lay-out worden genoemd?');
        cy.contains('.sw-button--primary', 'Lay-out maken').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('Package Product Page');
        cy.contains('.sw-button--primary', 'Lay-out maken').should('be.enabled');
        cy.contains('.sw-button--primary', 'Lay-out maken').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-cms-section__wrapper', 'Naam van het product').should('be.visible');

        // Assign layout to a product
        cy.get('button[title="Layout opdracht"]').click();
        cy.contains('.sw-button--ghost', 'Lay-out toewijzen').click();
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('input[placeholder="Producten zoeken en toewijzen..."]').type('Page Product');
        cy.contains('.sw-select-option--0', 'Page Product').click();
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveLayout').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Add product to sales channel
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('h2', 'Page Product').should('be.visible');
        cy.get('.sw-product-detail__select-visibility').scrollIntoView()
            .typeMultiSelectAndCheck(Cypress.env('storefrontName'));

        // Upload image
        cy.get('.sw-tabs__content > a[title="Indeling"]').scrollIntoView().click();
        cy.get('.sw-cms-page-form__section > div:nth-of-type(3)').scrollIntoView();
        cy.get('.sw-cms-el-config-image-gallery__tab-content.sw-container .sw-cms-mapping-field__action-label').click();
        cy.get('.sw-media-list-selection-v2 .sw-media-upload-v2__content').should('be.visible');
        pageMedia.uploadImageUsingFileUpload('img/sw-login-background.png');
        cy.awaitAndCheckNotification('Een bestand opgeslagen.');

        // Save product
        cy.get('.sw-button-process__content').click();
        cy.wait('@editLayout').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-button-process__content', 'Opslaan').should('be.visible');

        // Verify layout in the storefront
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Page Product');
        cy.contains('.search-suggest-product-name', 'Page Product').click();
        cy.contains('h1', 'Page Product').should('be.visible');
        cy.get('.gallery-slider-image.img-fluid.js-magnifier-image.magnifier-image').should('be.visible');
        cy.get('.gallery-slider-image.img-fluid.js-magnifier-image.magnifier-image')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);

    });
});
