// / <reference types="Cypress" />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Product: Check cross selling integration', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Original product',
                    productNumber: 'RS-11111',
                    description: 'Pudding wafer apple pie fruitcake cupcake. Biscuit cotton candy gingerbread liquorice tootsie roll caramels soufflé. Wafer gummies chocolate cake soufflé.'
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Second product',
                    productNumber: 'RS-22222',
                    description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping. Cotton candy jelly beans tootsie roll pie tootsie roll chocolate cake brownie. I love pudding brownie I love.'
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Third product',
                    productNumber: 'RS-33333',
                    description: 'Cookie bonbon tootsie roll lemon drops soufflé powder gummies bonbon. Jelly-o lemon drops cheesecake. I love carrot cake I love toffee jelly beans I love jelly.'
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });
    });

    it('@catalogue: add cross selling stream to product', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-stream`,
            method: 'post'
        }).as('saveStream');

        // Open and adjust product stream
        cy.get('.sw-product-stream-list-grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.loader).should('not.exist');

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is equal to any of',
                value: ['Second product', 'Third product']
            }
        );

        cy.get('.sw-button-process').click();
        cy.wait('@saveStream').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Open product and add cross selling
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.contains('Original product').click();

        cy.get('.sw-product-detail__tab-cross-selling').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.contains(
            `.sw-product-detail-cross-selling__empty-state ${page.elements.ghostButton}`,
            'Add new Cross Selling'
        ).should('be.visible').click();
        cy.get('.product-detail-cross-selling-form').should('be.visible');

        // Fill in cross selling form
        cy.get('#sw-field--crossSelling-name').typeAndCheck('Kunden kauften auch');
        cy.get('#sw-field--crossSelling-product-group')
            .typeSingleSelectAndCheck(
                '1st Productstream',
                '#sw-field--crossSelling-product-group'
            );
        cy.get('input[name="sw-field--crossSelling-active"]').click();

        // Save and verify cross selling stream
        cy.get('.sw-button-process').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // check if add cross selling button is still visible
        cy.get('.sw-product-detail-cross-selling__add-btn').should('be.visible');

        // Verify in storefront
        cy.visit('/');
        cy.contains('Original product').click();
        cy.get('.product-detail-content').should('be.visible');
        cy.get('.product-detail-name').contains('Original product');

        cy.get('.product-cross-selling-tab-navigation')
            .scrollIntoView()
            .should('be.visible');
        cy.get('.product-detail-tab-navigation-link.active').contains('Kunden kauften auch');
        cy.get('.product-slider-item .product-name[title="Second product"]')
            .should('be.visible');
        cy.get('.product-slider-item .product-name[title="Third product"]')
            .should('be.visible');
    });

    it('@catalogue: add manual cross selling to product', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-stream`,
            method: 'post'
        }).as('saveStream');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-cross-selling/**/assigned-products`,
            method: 'post'
        }).as('assignProduct');

        // Open product and add cross selling
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.contains('Original product').click();

        cy.get('.sw-product-detail__tab-cross-selling').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.contains(
            `.sw-product-detail-cross-selling__empty-state ${page.elements.ghostButton}`,
            'Add new Cross Selling'
        ).should('be.visible').click();
        cy.get('.product-detail-cross-selling-form').should('be.visible');

        // Fill in cross selling form
        cy.get('#sw-field--crossSelling-name').typeAndCheck('Kunden kauften auch');
        cy.get('#sw-field--crossSelling-type').select('Manual selection');
        cy.get('input[name="sw-field--crossSelling-active"]').click();

        // Save and verify cross selling stream
        cy.get('.sw-button-process').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // check if add cross selling button is still visible
        cy.get('.sw-product-detail-cross-selling__add-btn').should('be.visible');

        // Add products to cross selling
        cy.get('.sw-product-cross-selling-assignment__select-container .sw-entity-single-select__selection').type('Second');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'Second product').click();
        cy.get('.sw-card__title').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-data-grid__cell--product-translated-name').contains('Second product');

        // Add more products to cross selling
        cy.get('.sw-product-cross-selling-assignment__select-container .sw-entity-single-select__selection').type('Third');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'Third product').click();
        cy.get('.sw-card__title').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-data-grid__cell--product-translated-name').contains('Third product');

        // Swap positions
        cy.get('.sw-data-grid__row--1 .sw-data-grid-column-position__arrow.arrow_up').should('be.visible');
        cy.get('.sw-data-grid__row--1 .sw-data-grid-column-position__arrow.arrow_up').should('not.be.disabled');
        cy.get('.sw-data-grid__row--1 .sw-data-grid-column-position__arrow.arrow_up').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-data-grid__row--0').contains('Third product');
        cy.get('.sw-data-grid__row--1').contains('Second product');

        // Save and verify cross selling stream
        cy.get('.sw-button-process').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // check if add cross selling button is still visible
        cy.get('.sw-product-detail-cross-selling__add-btn').should('be.visible');

        // Verify in storefront
        cy.visit('/');
        cy.contains('Original product').click();
        cy.get('.product-detail-content').should('be.visible');
        cy.get('.product-detail-name').contains('Original product');

        cy.get('.product-cross-selling-tab-navigation')
            .scrollIntoView()
            .should('be.visible');
        cy.get('.product-detail-tab-navigation-link.active').contains('Kunden kauften auch');
        cy.get('#tns1-item1 .product-name').contains('Second product');
        cy.get('#tns1-item0 .product-name').contains('Third product');
    });

    it('@catalogue: should handle required fields', () => {
        const page = new ProductStreamObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-stream`,
            method: 'post'
        }).as('saveStream');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product-cross-selling/**/assigned-products`,
            method: 'post'
        }).as('assignProduct');

        // Open product and add cross selling
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.contains('Original product').click();

        cy.get('.sw-product-detail__tab-cross-selling').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.contains(
            `.sw-product-detail-cross-selling__empty-state ${page.elements.ghostButton}`,
            'Add new Cross Selling'
        ).should('be.visible').click();
        cy.get('.product-detail-cross-selling-form').should('be.visible');

        // Save and verify cross selling stream
        cy.get('.sw-button-process').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 400);
        });

        // check if add cross selling button is still visible
        cy.get('.sw-product-detail-cross-selling__add-btn').should('be.visible');

        cy.get('.sw-tabs__content').contains('.sw-tabs-item', 'Cross Selling').then((field) => {
            cy.wrap(field).should('have.class', 'sw-tabs-item--has-error');
        });

        cy.get('.sw-field').contains('.sw-field', 'Name').then((field) => {
            cy.wrap(field).should('have.class', 'has--error');
            cy.get('input', { withinSubject: field }).type('1').blur();
            cy.wrap(field).should('not.have.class', 'has--error');
        });
    });
});
