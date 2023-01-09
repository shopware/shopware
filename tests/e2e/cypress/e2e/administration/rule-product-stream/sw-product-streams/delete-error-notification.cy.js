// / <reference types="Cypress" />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Test notification on failed delete', () => {
    beforeEach(() => {
        cy.createDefaultFixture('product-stream', {
            categories: [{ name: 'first' }, { name: 'second' }],
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
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

        // Expect error response and notification being shown
        cy.wait('@deleteData').its('response.statusCode').should('equal', 500);
        cy.awaitAndCheckNotification('"1st Productstream" provides product assignments for 2 categories and thus cannot be deleted.');
    });
});
