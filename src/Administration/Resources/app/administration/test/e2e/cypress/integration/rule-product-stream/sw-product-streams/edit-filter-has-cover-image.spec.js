// / <reference types="Cypress" />

import ProductStreamObject from "../../../support/pages/module/sw-product-stream.page-object";

describe('Dynamic product group: Test product has cover image filter with and without cover image', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Product with image',
                    productNumber: 'SW-11111',
                    cover: {
                        media: {
                            alt: 'Lorem Ipsum dolor',
                            url: 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='
                        }
                    }
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Product without image',
                    productNumber: 'SW-11112',
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/create`);
            });
    });

    it('@catalogue: filters products by cover image existing', () => {
        const page = new ProductStreamObject();
        cy.get('.sw-product-stream-filter').as('productStreamFilterHasCoverImage');

        // set filter to only include products with cover image
        page.fillFilterWithSelect(
            '@productStreamFilterHasCoverImage',
            {
                field: 'Has cover image',
                operator: null,
                value: 'Yes'
            }
        );

        // open preview
        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-product-stream-modal-preview').should('be.visible');

        // should only preview one product with cover image
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (1)');
            cy.get('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Product with image');
            cy.get('.sw-modal__close').click();
        });

        // set filter to only include products without cover image
        page.fillFilterWithSelect(
            '@productStreamFilterHasCoverImage',
            {
                field: 'Has cover image',
                operator: null,
                value: 'No'
            }
        );

        // open preview
        cy.get('button.sw-button').contains('Preview').click();
        cy.get('.sw-product-stream-modal-preview').should('be.visible');

        // should only preview one product with cover image
        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-modal__header').contains('Preview (1)');
            cy.get('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Product without image');
            cy.get('.sw-modal__close').click();
        });
    });
});
