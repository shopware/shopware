// / <reference types="Cypress" />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Test crud operations', () => {
    beforeEach(() => {
        cy.createDefaultFixture('product-stream').then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @catalogue: create and read dynamic product group', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-stream`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-product-stream-list__create-action').click();

        // Create new product stream with basic condition
        cy.get('input[name=sw-field--productStream-name]').typeAndCheck('01st1st Productstream');
        cy.get('textarea[name=sw-field--productStream-description]').type('My first product stream');
        cy.get(page.elements.streamSaveAction).click();

        // Verify property in listing
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('01st1st Productstream');
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, '01st1st Productstream');
    });

    it('@base @catalogue: update and read dynamic product group', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-stream/*`,
            method: 'PATCH',
        }).as('saveData');

        // Edit product stream
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.get('input[name=sw-field--productStream-name]').clearTypeAndCheck('Streamline');
        cy.get(page.elements.streamSaveAction).click();

        // Verify property in listing
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Streamline');
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Streamline');
    });

    it('@base @catalogue: delete dynamic product group', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later

        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-stream/*`,
            method: 'delete',
        }).as('deleteData');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('1st Productstream');
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, '1st Productstream');

        // Delete dynamic product group
        // Edit product stream
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.contains('button.sw-button', 'Delete').click();

        // Verify property in listing
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
        cy.get(`${page.elements.dataGridRow}--0`).should('not.exist');
    });

    it('@base @catalogue: duplicate and read dynamic product group', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Requests we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/product-stream/*`,
            method: 'POST',
        }).as('cloneData');

        // Edit product stream
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(page.elements.loader).should('not.exist');

        // Add filter
        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        page.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'Yes',
            },
        );

        // Save product stream
        cy.get(page.elements.streamSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.smartBarBack).click();

        // Duplicate product stream
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-duplicate',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.wait('@cloneData').its('response.statusCode').should('equal', 200);
        cy.get(page.elements.loader).should('not.exist');

        // Verify product stream name has been appended
        cy.get('input[name=sw-field--productStream-name]').should('have.value', '1st Productstream Copy');

        // Verify that filters of original have been cloned as well
        cy.get('.sw-product-stream-field-select .sw-single-select__selection-text').should('contain', 'Active');
        cy.get('.sw-product-stream-value .sw-single-select__selection-text').should('contain', 'Yes');

        // Modify name before save and duplicate
        cy.get('input[name=sw-field--productStream-name]').clearTypeAndCheck('Testing before clone');

        // Click save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-stream-detail__save-duplicate-action',
            '.sw-product-stream-detail__save-button-group .sw-context-button',
        );

        // Verify product stream name has been appended
        cy.get('input[name=sw-field--productStream-name]').should('have.value', 'Testing before clone Copy');
    });
});
