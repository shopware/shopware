// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit product media', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: change media sorting', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');

        // Open product
        cy.get('.sw-product-list-grid').should('be.visible');
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

        // Add second image to product
        cy.get('#files')
            .attachFile('img/sw-test-image.png');
        cy.get('.sw-product-image:nth-of-type(2) img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-product-image:nth-of-type(2)').dragTo('.sw-product-image:nth-of-type(1)');
        cy.get('.sw-product-image img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/Product-name/RS-333');
        cy.get('.gallery-slider-item').should('be.visible');
        cy.get('#tns2-item0 img')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
    });

    it('@base @catalogue: set another cover image', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');

        // Open product
        cy.get('.sw-product-list-grid').should('be.visible');
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

        // Add second image to product
        cy.get('#files')
            .attachFile('img/sw-test-image.png');
        cy.get('.sw-product-image:nth-of-type(2) img')
            .first()
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        // Add third image to product
        cy.get('#files')
            .attachFile('img/plugin-manager--login.png');
        cy.get('.sw-product-image:nth-of-type(3) img')
            .first()
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);

        // Change cover image
        cy.get(`.sw-product-image:nth-of-type(3) ${page.elements.contextMenuButton}`)
            .click({ force: true });
        cy.contains('Use as cover').click();
        cy.get('.sw-product-image:nth-of-type(3) .sw-label--primary').should('be.visible');
        cy.get('.sw-product-media-form__cover-image img')
            .first()
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');
        cy.get('.product-image-wrapper img')
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);
        cy.get('.product-name').click();
        cy.get('.gallery-slider-item').should('be.visible');
        cy.get('.tns-nav-active').should('be.visible');
        cy.get('#tns1-item2 img')
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);
    });

    it('@catalogue: remove a product\'s image', () => {
        const page = new ProductPageObject();

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

        cy.get('.sw-product-image__image img')
            .should('have.attr', 'src')
            .and('match', /sw_logo_white|sw-login-background/);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);

        // Remove image
        cy.get(`.sw-product-image:nth-of-type(1) ${page.elements.contextMenuButton}`)
            .click({ force: true });
        cy.contains('Remove').click();
        cy.get('.sw-product-media-form__cover-image.is--placeholder').should('be.visible');

        // Save product
        cy.get(page.elements.productSaveAction).click();

        // Verify removal
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Product name').click();

        cy.get('.sw-product-media-form__previews').scrollIntoView();
        cy.get('.sw-product-media-form__cover-image.is--placeholder').should('be.visible');
    });
});
