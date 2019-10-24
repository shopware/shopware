// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit a products description in various ways', () => {
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

    it.skip('@catalogue: test the text editor\'s link functionality', () => {
        const page = new ProductPageObject();
        const productName = 'This is an example product with links';
        const exampleDomain = 'example.com';
        const exampleUrl = `https://${exampleDomain}`;
        const linkSelector = `a[href$="${exampleDomain}"]`;
        const buttonSelector = `${linkSelector}.btn.btn-primary.btn-sm`;

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/product/*',
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Rename product
        cy.get('input[name=sw-field--product-name]').as('productNameField').type(`{selectall}${productName}`);

        // Test link
        cy.get('.sw-text-editor .sw-text-editor__content-editor').as('textEditor').click().type('{selectall}Link{selectall}');
        cy.get('.sw-text-editor-toolbar .icon--default-text-editor-link').as('toolbarLinkButton').click();

        cy.get('.sw-text-editor-toolbar-button__link-menu').as('toolbarLinkOverlay').should('be.visible');

        cy.get('#sw-field--buttonConfig-value').as('linkUrlField').click().type(`{selectall}${exampleUrl}`);
        cy.get('.sw-text-editor-toolbar-button__link-menu-buttons button').as('insertButton').click();

        cy.get('@textEditor').scrollIntoView();
        cy.get(`.sw-text-editor ${linkSelector}`).should('be.visible');

        // Test link as button
        cy.get('@textEditor').click().type('{selectall}Primary small{selectall}');
        cy.get('@toolbarLinkButton').click();

        cy.get('@toolbarLinkOverlay').should('be.visible');

        cy.get('@linkUrlField').click().type(`{selectall}${exampleUrl}`);

        cy.get('#sw-field--buttonConfig-displayAsButton').as('displayAsButtonToggleField').click();
        cy.get('#sw-field--buttonConfig-buttonVariant').as('buttonVariantSelectField').should('be.visible');
        cy.get('@buttonVariantSelectField').select('primary-sm');

        cy.get('@insertButton').click();

        cy.get('@textEditor').scrollIntoView();
        cy.get(`.sw-text-editor ${buttonSelector}`).should('be.visible');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify in the storefront
        cy.visit('/');
        cy.get('input[name=search]').type(productName);
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name').contains(productName).click();
        cy.get('.product-detail-name').contains(productName);
        cy.get(`.product-detail-description-text ${buttonSelector}`).should('exist');
    });
});
