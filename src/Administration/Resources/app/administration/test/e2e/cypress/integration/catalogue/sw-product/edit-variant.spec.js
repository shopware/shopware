/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import PropertyPageObject from '../../../support/pages/module/sw-property.page-object';

describe('Product: Test variants', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createDefaultFixture('tax', {
                    id: "91b5324352dc4ee58ec320df5dcf2bf4",
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
                })
            })
            .then((saleschannel) => {
                 cy.createDefaultFixture('product', {
                     visibilities: [{
                         visibility: 30,
                         salesChannelId: saleschannel.id,
                     }]
                 } ,'product-variants.json');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: variants display corresponding name based on specific language', () => {
        const page = new PropertyPageObject();

        cy.visit(`${Cypress.env('admin')}#/sw/property/index`);

        cy.route({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'post'
        }).as('searchUserConfig');

        // Add option to property group
        cy.wait('@searchUserConfig').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.clickContextMenuItem(
                '.sw-property-list__edit-action',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );
        });

        cy.get(page.elements.cardTitle).contains('Basic information');

        // Switch language to Deutsch
        cy.get('.sw-language-switch__select .sw-entity-single-select__selection-text').contains('English');
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        // poor assertion to check if there is more than 1 language
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length.greaterThan', 1);
        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .contains('Deutsch').click();

        // Edit and update property option's name for Deutsch
        cy.get('.sw-property-option-list').scrollIntoView();

        const redOption = cy.get('.sw-property-option-list').contains('Red').parents('tr');
        redOption.dblclick();
        redOption.get('#sw-field--item-name').typeAndCheck('Rot');
        redOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        const greenOption = cy.get('.sw-property-option-list').contains('Green').parents('tr');
        greenOption.dblclick();
        greenOption.get('#sw-field--item-name').typeAndCheck('Grün');
        greenOption.get('.sw-button.sw-data-grid__inline-edit-save').click();

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);

        const productPage = new ProductPageObject();

        // Navigate to variant generator listing and start
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            productPage.elements.contextMenuButton,
            `${productPage.elements.dataGridRow}--0`
        );
        cy.get('.sw-product-detail__tab-variants').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Rot');
        cy.get('.sw-data-grid__body').contains('Grün');

        // Switch to English
        cy.get('.smart-bar__content .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').contains('English');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get(productPage.elements.loader).should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Green');

        cy.reload();

        cy.get('.sw-product-variants-overview').should('be.visible');

        cy.get('.sw-data-grid__body').contains('Red');
        cy.get('.sw-data-grid__body').contains('Green');
    });

    it('@catalogue: check fields in inheritance', () => {
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

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        // Check field inheritance in variant
        cy.get('.sw-product-variants-overview__single-variation').contains('Red').click();
        cy.get('.sw-product-variant-info__product-name').contains('Variant product name');

        cy.get('.sw-product-basic-form__inheritance-wrapper-description')
            .find('.sw-inheritance-switch--is-inherited')
            .scrollIntoView()
            .should('be.visible');

        // remove inheritance
        cy.get('.sw-product-basic-form__inheritance-wrapper-description')
            .find('.sw-inheritance-switch--is-inherited')
            .scrollIntoView()
            .click();


        // check if inheritance is removed
        cy.get('.sw-product-basic-form__inheritance-wrapper-description')
            .find('.sw-inheritance-switch--is-not-inherited')
            .scrollIntoView()
            .should('be.visible');
    });
});
