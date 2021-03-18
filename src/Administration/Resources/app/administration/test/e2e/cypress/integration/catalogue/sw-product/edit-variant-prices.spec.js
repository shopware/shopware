// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Test variants', () => {
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
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('unit');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it.skip('@catalogue: add variant with surcharge to product', () => {
        cy.window().then((win) => {
            const page = new ProductPageObject();

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/product/*`,
                method: 'patch'
            }).as('saveData');
            cy.route({
                url: `${Cypress.config('baseUrl')}/detail/**/switch?options=*`,
                method: 'get'
            }).as('changeVariant');

            // Navigate to variant generator listing and start
            cy.clickContextMenuItem(
                '.sw-entity-listing__context-menu-edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );

            cy.get('.sw-product-detail__tab-variants').click();
            cy.get(page.elements.loader).should('not.exist');
            cy.get(`.sw-product-detail-variants__generated-variants__empty-state ${page.elements.ghostButton}`)
                .should('be.visible')
                .click();
            cy.get('.sw-product-modal-variant-generation').should('be.visible');

            // Create and verify one-dimensional variant
            page.generateVariants('Color', [0, 1, 2], 3);
            cy.get('.sw-product-variants-overview').should('be.visible');

            cy.get('.sw-data-grid__body').contains('Red');
            cy.get('.sw-data-grid__body').contains('Yellow');
            cy.get('.sw-data-grid__body').contains('Green');
            cy.get('.sw-data-grid__body').contains('.1');
            cy.get('.sw-data-grid__body').contains('.2');
            cy.get('.sw-data-grid__body').contains('.3');

            // Get green variant
            cy.get('.sw-simple-search-field--form').should('be.visible');
            cy.get('.sw-simple-search-field--form input').typeAndCheck('Green');
            cy.get('.sw-data-grid-skeleton').should('not.exist');
            cy.get('.sw-data-grid__row--1').should('not.exist');
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Green');

            // Set surcharge
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').should('be.visible');
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({ force: true });
            cy.get('.is--inline-edit .sw-data-grid__cell--price-EUR .sw-inheritance-switch').should('be.visible');
            cy.get('.is--inline-edit .sw-data-grid__cell--price-EUR .sw-inheritance-switch').click();
            cy.get('.sw-data-grid__cell--price-EUR #sw-price-field-gross').should('be.visible');
            cy.get('.sw-data-grid__cell--price-EUR #sw-price-field-gross').should('be.enabled');
            cy.get('.sw-data-grid__cell--price-EUR #sw-price-field-gross').clearTypeAndCheck('100');
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--price-EUR .icon--default-lock-open').click();
            cy.get('.sw-data-grid__cell--price-EUR #sw-price-field-net')
                .invoke('val')
                .should('eq', '84.033613445378');
            cy.get('.icon--custom-uninherited').should('be.visible');
            cy.get('.sw-data-grid__inline-edit-save').click();

            // Validate product
            cy.wait('@productCall').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
                cy.awaitAndCheckNotification('Product "Green" has been saved.');
            });

            // Validate in Storefront
            cy.visit('/');
            cy.get('.product-image-wrapper').click();
            cy.get('.product-detail-buy').should('be.visible');

            // Ensure that variant "Green" is checked at the moment the test runs
            cy.get('.product-detail-configurator-option-label[title="Green"]').then(($btn) => {
                const inputId = $btn.attr('for');

                cy.get(`#${inputId}`).then(($input) => {
                    if (!$input.attr('checked')) {
                        cy.contains('Green').click();

                        cy.wait('@changeVariant').then((xhr) => {
                            expect(xhr).to.have.property('status', 200);
                            cy.get('.product-detail-price').contains('100.00');
                        });
                    } else {
                        cy.log('Variant "Green" is already open.');
                        cy.get('.product-detail-price').contains('100.00');
                    }
                });
            });

            cy.contains('Red').click();

            cy.wait('@changeVariant').then((xhr) => {
                expect(xhr).to.have.property('status', 200);

                cy.get('.product-detail-price').contains('64.00');
            });
        });
    });
});
