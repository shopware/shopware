/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test crud operations', () => {
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

    it('@base @catalogue: create and read product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'post'
        }).as('calculatePrice');

        // Add basic data to product
        cy.get('a[href="#/sw/product/create"]').click();

        cy.get('input[name=sw-field--product-name]').typeAndCheck('Product with file upload image');
        cy.get('.sw-select-product__select_manufacturer')
            .typeSingleSelectAndCheck('shopware AG', '.sw-select-product__select_manufacturer');
        cy.get('select[name=sw-field--product-taxId]').select('Standard rate');
        cy.get('#sw-price-field-gross').type('10');

        if (Cypress.isBrowser({ family: 'chromium' })) {
            // Add image to product
            cy.fixture('img/sw-login-background.png').then(fileContent => {
                cy.get('#files').upload(
                    {
                        fileContent,
                        fileName: 'sw-login-background.png',
                        mimeType: 'image/png'
                    }, {
                        subjectType: 'input'
                    }
                );
            });
            cy.get('.sw-product-image__image img')
                .should('have.attr', 'src')
                .and('match', /sw-login-background/);
            cy.awaitAndCheckNotification('File has been saved.');
        }

        // Check net price calculation
        cy.wait('@calculatePrice').then(() => {
            cy.get('#sw-price-field-net').should('have.value', '8.4');
        });

        cy.get('input[name=sw-field--product-stock]').type('100');

        // Set product visible
        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-product-detail__select-visibility .sw-select-selection-list__input')
            .type('{esc}');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Product with file upload image');

        // Verify in storefront
        cy.visit('/');
        cy.get('input[name=search]').type('Product with file upload image');
        cy.get('.search-suggest-container').should('be.visible');
        cy.get('.search-suggest-product-name')
            .contains('Product with file upload image')
            .click();
        cy.get('.product-detail-name').contains('Product with file upload image');
        cy.get('.product-detail-price').contains('10.00');
    });

    it('@base @catalogue: update and read product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('input[name=sw-field--product-name]').clearTypeAndCheck('What remains of Edith Finch');
        cy.get('input[name=sw-field--product-active]').click();
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('What remains of Edith Finch');
    });

    it('@base @catalogue: delete product', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'delete'
        }).as('deleteData');

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-listing__confirm-delete-text`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify updated product
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
