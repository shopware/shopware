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
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');
        cy.route({
            url: '/api/**/search/product',
            method: 'POST'
        }).as('getProduct');

        // Duplicate product by using context menu option
        cy.clickContextMenuItem(
            '.sw-product-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify product
        cy.wait('@duplicateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.wait('@getProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.smart-bar__header').contains('Product name Copy');
            cy.contains('.sw-button', 'Cancel').click();
            cy.get('.sw-page__smart-bar-amount').contains('2');
        });
    });

    it('@base @catalogue: duplicate product in product-detail', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST'
        }).as('getProduct');

        // Open product to duplicate
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@getProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('What remains of Edith Finch');

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-context-button > .sw-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'What remains of Edith Finch Copy'
        );

        cy.contains('Cancel').click();
        cy.get('.sw-data-grid').should('be.visible');
        cy.get('.sw-data-grid__cell--name').contains('What remains of Edith Finch Copy');
        cy.get('.sw-data-grid__cell--name').contains('What remains of Edith Finch');
    });

    it('@base @catalogue: duplicate duplicated product in product-detail', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/clone/product/*`,
            method: 'POST'
        }).as('duplicateProduct');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST'
        }).as('getProduct');

        // Open product to duplicate
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.wait('@getProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('What remains of Edith Finch');

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-context-button > .sw-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('input[name=sw-field--product-name]').should(
            'have.value',
            'What remains of Edith Finch Copy'
        );

        // Save and duplicate
        cy.clickContextMenuItem(
            '.sw-product-detail__save-duplicate-action',
            '.sw-context-button > .sw-button'
        );

        // Verify product
        cy.wait('@duplicateProduct').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
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
