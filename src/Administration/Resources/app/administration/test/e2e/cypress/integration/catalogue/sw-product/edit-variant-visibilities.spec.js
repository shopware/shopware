// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants visibilities', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createDefaultFixture('tax', {
                    id: '91b5324352dc4ee58ec320df5dcf2bf4'
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{
                        id: '15532b3fd3ea4c1dbef6e9e9816e0715',
                        name: 'Red'
                    }, {
                        id: '98432def39fc4624b33213a56b8c944d',
                        name: 'Green'
                    }]
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }]
                });
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Storefront'
                    },
                    endpoint: 'sales-channel'
                });
            })
            .then((saleschannel) => {
                cy.createDefaultFixture('product', {
                    visibilities: [{
                        visibility: 30,
                        salesChannelId: saleschannel.id
                    }]
                }, 'product-variants.json');
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
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Green');
        cy.get('.sw-data-grid__body').contains('Green').click();

        // remove inherited
        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-category-form__visibility_field .sw-inheritance-switch').click();
        cy.get('.sw-product-detail__select-visibility')
            .typeMultiSelectAndCheckMultiple(['Headless']);

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get('.sw-product-detail__select-visibility')
                .scrollIntoView();
            cy.get('.sw-product-detail__select-visibility')
                .should('contain', 'Headless');
        });

        cy.get('.sw-card__back-link').scrollIntoView();
        cy.get('.sw-card__back-link').should('be.visible');
        // cy.wait(1);
        cy.get('.sw-card__back-link').click({ waitForAnimations: false });

        cy.get('.sw-product-detail__tab-general').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-product-detail__select-visibility')
            .scrollIntoView();
        cy.get('.sw-product-detail__select-visibility')
            .should('contain', 'Storefront');
    });
});
