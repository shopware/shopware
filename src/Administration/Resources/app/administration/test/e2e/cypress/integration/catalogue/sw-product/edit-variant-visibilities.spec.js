// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants visibilities', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }]
                });
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: edit visibilities', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility')
            .typeMultiSelectAndCheckMultiple(['Storefront']);

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get('.sw-product-detail__select-visibility')
                .scrollIntoView();
            cy.get('.sw-product-detail__select-visibility')
                .should('contain', 'Storefront');
        });

        // switch variants tab
        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
            .should('be.visible')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');

        // Create and verify one-dimensional variant
        page.generateVariants('Color', [0, 1, 2], 3);
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Yellow');
        cy.get('.sw-data-grid__body').contains('Yellow').click();

        // remove inherited
        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-category-form__visibility_field .sw-inheritance-switch').click();
        cy.get('.sw-product-detail__select-visibility')
            .typeMultiSelectAndCheckMultiple(['Headless']);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@productCall').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get('.sw-product-detail__select-visibility')
                .scrollIntoView();
            cy.get('.sw-product-detail__select-visibility')
                .should('contain', 'Headless');
        });

        cy.get('.sw-card__back-link').click();

        cy.get('.sw-product-detail__tab-general').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility')
            .should('contain', 'Storefront');
    });
});
