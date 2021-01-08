// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Editing context prices', () => {
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

    it('@base @rule @product: creates context price rules', () => {
        cy.window().then(() => {
            const page = new ProductPageObject();
            const priceGroup = '.context-price-group';
            const priceCell = '.sw-data-grid__cell--price';
            const emptySelectRule = '.sw-product-detail-context-prices__empty-state-select-rule';

            // input values
            const quantityEnd00 = 20;
            const quantityEnd01 = 40;
            const priceGross02EUR = 199;
            const priceGross11USD = 999;

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/product/*`,
                method: 'patch'
            }).as('saveData');

            // Open the product
            cy.clickContextMenuItem(
                '.sw-entity-listing__context-menu-edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );

            // Go to context prices
            cy.get('.sw-product-detail__tab-advanced-prices')
                .click();

            // Check if empty state is correctly displayed
            cy.get('.sw-product-detail-context-prices__empty-state img').should('be.visible');
            cy.get('.sw-product-detail-context-prices__empty-state p').should('be.visible');

            // Select price rule group
            cy.get(`${emptySelectRule}`)
                .typeSingleSelect('All customers', `${emptySelectRule}`);

            // Disable list prices
            cy.get('.sw-data-grid__cell-content > .sw-context-button > .sw-button').click();
            cy.get('.sw-context-menu__content > :nth-child(1)').should('be.visible');
            cy.contains('Show list prices').should('be.visible');
            cy.contains('Show list prices').click();
            cy.get('.sw-data-grid__cell-content > .sw-context-button > .sw-button').click();

            // change quantityEnd of first rule
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--0 input[name="sw-field--item-quantityEnd"]`)
                .scrollIntoView()
                .type(`${quantityEnd00}{enter}`);

            // change quantityEnd of second rule
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 input[name="sw-field--item-quantityEnd"]`)
                .scrollIntoView()
                .type(`${quantityEnd01}{enter}`);

            // Change price in third rule
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 ${priceCell}-EUR input[name="sw-price-field-gross"]`)
                .scrollIntoView()
                .clear()
                .type(`${priceGross02EUR}{enter}`);

            // Add price link in third rule
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 ${priceCell}-EUR .sw-price-field__lock`)
                .scrollIntoView()
                .click();

            // Uninherit the US-Dollar price in second rule in second price group
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 ${priceCell}-USD .sw-inheritance-switch`)
                .scrollIntoView()
                .click();

            // Add custom dollar price to second rule in second price group
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 ${priceCell}-USD input[name="sw-price-field-gross"]`)
                .scrollIntoView()
                .clear()
                .type(`${priceGross11USD}{enter}`);

            // Duplicate Price Rule
            cy.get(`${priceGroup}-0 .sw-product-detail-context-prices__toolbar-duplicate`)
                .click();

            // Check if second price group exists
            cy.get(`${priceGroup}-1`)
                .should('be.visible');

            // Add rule to second price rule group
            cy.get(`${priceGroup}-1 .sw-product-detail-context-prices__toolbar .sw-product-detail-context-prices__toolbar-selection`)
                .typeSingleSelect('Sunday sales', `${priceGroup}-1 .sw-product-detail-context-prices__toolbar-selection`);

            // Save price rule groups
            cy.get(page.elements.productSaveAction).click();

            // Check if values matches the inputs
            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);

                // second price group should be visible
                cy.get(`${priceGroup}-1`)
                    .should('be.visible');

                // check if all fields saved successfully
                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--0 input[name="sw-field--item-quantityEnd"]`)
                    .should('be.visible');
                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--0 input[name="sw-field--item-quantityEnd"]`)
                    .should('have.value', `${quantityEnd00}`);

                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 input[name="sw-field--item-quantityEnd"]`)
                    .should('have.value', `${quantityEnd01}`);

                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 ${priceCell}-EUR input[name="sw-price-field-gross"]`)
                    .scrollIntoView()
                    .should('have.value', `${priceGross02EUR}`);

                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 ${priceCell}-EUR .sw-price-field__lock.is--locked`)
                    .scrollIntoView()
                    .should('be.visible');

                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 ${priceCell}-USD .sw-inheritance-switch .icon--custom-uninherited`)
                    .scrollIntoView()
                    .should('be.visible');

                cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 ${priceCell}-USD input[name="sw-price-field-gross"]`)
                    .scrollIntoView()
                    .should('have.value', `${priceGross11USD}`);
            });

            // change quantityStart in first price group and third price rule to an unallowed value
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 input[name="sw-field--item-quantityStart"]`)
                .clear()
                .type(`${quantityEnd01 / 2}{enter}`);

            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 input[name="sw-field--item-quantityEnd"]`)
                .click();

            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--2 input[name="sw-field--item-quantityStart"]`)
                .should('have.value', `${quantityEnd01 + 1}`);

            // delete a rule in the middle
            cy.clickContextMenuItem(
                '.product-detail-context-prices__context-delete',
                page.elements.contextMenuButton,
                `${priceGroup}-0 ${page.elements.dataGridRow}--1`
            );

            // check if other values in price group were adjusted to the deletion
            cy.get(`${priceGroup}-0 ${page.elements.dataGridRow}--1 input[name="sw-field--item-quantityStart"]`)
                .should('have.value', `${quantityEnd00 + 1}`);

            // delete a rule in the beginning
            cy.clickContextMenuItem(
                '.product-detail-context-prices__context-delete',
                page.elements.contextMenuButton,
                `${priceGroup}-1 ${page.elements.dataGridRow}--0`
            );

            // check if new first rule in price group were adjusted to the deletion
            cy.get(`${priceGroup}-1 ${page.elements.dataGridRow}--0 input[name="sw-field--item-quantityStart"]`)
                .should('have.value', '1');
        });
    });
});
