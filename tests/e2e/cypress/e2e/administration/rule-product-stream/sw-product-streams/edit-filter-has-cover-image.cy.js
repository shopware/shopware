// / <reference types="Cypress" />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product group: Test product has cover image filter with and without cover image', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Product with image',
            productNumber: 'SW-11111',
            cover: {
                media: {
                    alt: 'Lorem Ipsum dolor',
                    url: 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==',
                },
            },
        }).then(() => {
            return cy.createProductFixture({
                name: 'Product without image',
                productNumber: 'SW-11112',
            });
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/create`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@catalogue: filters products by cover image existing', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();
        cy.get('.sw-product-stream-filter').as('productStreamFilterHasCoverImage');

        // set filter to only include products with cover image
        page.fillFilterWithSelect(
            '@productStreamFilterHasCoverImage',
            {
                field: 'Has cover image',
                operator: null,
                value: 'Yes',
            },
        );

        // open preview
        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-product-stream-modal-preview').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // should only preview one product with cover image
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (1)');
            cy.contains('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name', 'Product with image');
            cy.get('.sw-modal__close').click();
        });

        // set filter to only include products without cover image
        page.fillFilterWithSelect(
            '@productStreamFilterHasCoverImage',
            {
                field: 'Has cover image',
                operator: null,
                value: 'No',
            },
        );

        // open preview
        cy.contains('button.sw-button', 'Preview').click();
        cy.get('.sw-product-stream-modal-preview').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // should only preview one product with cover image
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.contains('.sw-modal__header', 'Preview (1)');
            cy.contains('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name', 'Product without image');
            cy.get('.sw-modal__close').click();
        });
    });
});
