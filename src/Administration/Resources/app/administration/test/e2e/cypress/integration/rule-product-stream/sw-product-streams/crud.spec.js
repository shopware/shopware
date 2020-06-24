/// <reference types="Cypress" />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });
    });

    it('@base @catalogue: create and read dynamic product group', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-stream`,
            method: 'post'
        }).as('saveData');

        cy.get('.sw-product-stream-list__create-action').click();

        // Create new product stream with basic condition
        cy.get('input[name=sw-field--productStream-name]').typeAndCheck('01st1st Productstream');
        cy.get('textarea[name=sw-field--productStream-description]').type('My first product stream');
        cy.get(page.elements.streamSaveAction).click();

        // Verify property in listing
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('01st1st Productstream');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('01st1st Productstream');
    });

    it('@base @catalogue: update and read dynamic product group', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-stream/*`,
            method: 'patch'
        }).as('saveData');

        // Edit product stream
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.get('input[name=sw-field--productStream-name]').clearTypeAndCheck('Streamline');
        cy.get(page.elements.streamSaveAction).click();

        // Verify property in listing
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Streamline');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Streamline');
    });

    it('@base @catalogue: delete dynamic product group', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-stream/*`,
            method: 'delete'
        }).as('deleteData');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('1st Productstream');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('1st Productstream');

        // Delete dynamic product group
        // Edit product stream
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('button.sw-button').contains('Delete').click();

        // Verify property in listing
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(`${page.elements.dataGridRow}--0`).should('not.exist');
    });
});
