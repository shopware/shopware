// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Duplicate product', () => {
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

    it('@base @catalogue: duplicate product in product-list', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/**/search/product`,
            method: 'POST'
        }).as('getProduct');

        // Duplicate product by using context menu option
        cy.clickContextMenuItem(
            '.sw-product-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify product
        cy.url().should('contain', '/product/detail/');
        cy.wait('@duplicateProduct').its('response.statusCode').should('equal', 200);
        cy.wait('@getProduct').its('response.statusCode').should('equal', 200);
        cy.get('.smart-bar__content').should('be.visible');
        cy.get('.smart-bar__header').contains('Product name Copy');
        cy.contains('.sw-button', 'Cancel').click();
        cy.get('.sw-page__smart-bar-amount').contains('2');
    });

    it('@base @catalogue: duplicate product in product-detail', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST'
        }).as('getProduct');

        // Open product to duplicate
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@getProduct').its('response.statusCode').should('equal', 200);
        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('What remains of Edith Finch');

        // edit description
        cy.get('.sw-text-editor__content-editor')
            .clear()
            .type('Some random description');

        // edit price
        cy.get('#sw-price-field-gross').clearTypeAndCheck('1337');

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-product-detail__save-button-group .sw-context-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').its('response.statusCode').should('equal', 200);
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'What remains of Edith Finch Copy'
        );

        // check description
        cy.get('.sw-text-editor__content-editor')
            .invoke('text')
            .then(text => {
                expect(text).to.equal('Some random description');
            });

        // check price
        cy.get('#sw-price-field-gross')
            .invoke('val')
            .then(text => {
                expect(text).to.equal('1337');
            });

        // change name of copied product
        cy.get('#sw-field--product-name')
            .scrollIntoView()
            .clearTypeAndCheck('Copied product');

        cy.contains('Save').click();

        // verify save request got fired
        cy.wait('@saveProduct').its('response.statusCode').should('equal', 200);
        cy.contains('Cancel').click();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('What remains of Edith Finch');

        cy.contains('.sw-data-grid__cell-content', 'What remains of Edith Finch').click();

        // check product name
        cy.get('#sw-field--product-name')
            .invoke('val')
            .then(text => {
                expect(text).to.equal('What remains of Edith Finch');
            });

        // check description
        cy.get('.sw-text-editor__content-editor')
            .invoke('text')
            .then(text => {
                expect(text).to.equal('Some random description');
            });

        // check price
        cy.get('#sw-price-field-gross')
            .invoke('val')
            .then(text => {
                expect(text).to.equal('1337');
            });
    });

    it('@catalogue: duplicate duplicated product in product-detail', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST'
        }).as('getProduct');

        // Open product to duplicate
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@getProduct').its('response.statusCode').should('equal', 200);
        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('What remains of Edith Finch');

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-product-detail__save-button-group .sw-context-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').its('response.statusCode').should('equal', 200);
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'What remains of Edith Finch Copy'
        );

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-product-detail__save-button-group .sw-context-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').its('response.statusCode').should('equal', 200);
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'What remains of Edith Finch Copy Copy'
        );

        cy.contains('Cancel').click();
        cy.get('.sw-data-grid').should('be.visible');
        cy.get('.sw-data-grid__cell--name').contains('What remains of Edith Finch Copy Copy');
        cy.get('.sw-data-grid__cell--name').contains('What remains of Edith Finch Copy');
        cy.get('.sw-data-grid__cell--name').contains('What remains of Edith Finch');
    });
});
