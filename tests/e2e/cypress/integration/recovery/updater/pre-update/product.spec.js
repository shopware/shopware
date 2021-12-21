/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from "../../../support/pages/checkout.page-object";

describe('create product and add to cart', ()=>{

    beforeEach(() => {
        cy.setLocaleToEnGb().then(() => {
            cy.loginViaApi();
        });
    });

    it('@base @catalogue: should create product', ()=>{
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('saveData');
        cy.intercept({
            url: '/api/_action/calculate-price',
            method: 'post'
        }).as('calculatePrice');

        // General > General information
        cy.get('a[href="#/sw/product/create"]').click();
        cy.get('input[name=sw-field--product-name]').typeAndCheck('Product created before update');
        cy.get('#manufacturerId').typeSingleSelectAndCheck('Element','#manufacturerId');
        cy.get('.sw-text-editor__content-editor').type('This text is written before update');
        cy.get('.product-basic-form__switches').find('.sw-field--switch__input').click();

        // General > Prices
        //const rate = Cypress.env('locale') === 'en-GB' ? 'Standard rate' : 'Standard-Satz';
        cy.get('select[name=sw-field--product-taxId]').select('Standard-Satz');
        cy.get('.sw-list-price-field > :nth-child(1) #sw-price-field-gross').type('14.99');
        cy.get('.sw-list-price-field > :nth-child(1) #sw-price-field-gross').blur();
        cy.wait('@calculatePrice').its('response.statusCode').should('equal', 200);
        cy.get('.sw-list-price-field > :nth-child(1) #sw-price-field-net').should('have.value', '12.596638655462');

        cy.get('.sw-list-price-field > :nth-child(2) #sw-price-field-gross').type('9.99');
        cy.get('.sw-list-price-field > :nth-child(2) #sw-price-field-gross').blur();
        cy.wait('@calculatePrice').its('response.statusCode').should('equal', 200);
        cy.get('.sw-list-price-field > :nth-child(2) #sw-price-field-net').should('have.value', '8.3949579831933');

        cy.get('.sw-list-price-field > :nth-child(3) #sw-price-field-gross').type('19.99');
        cy.get('.sw-list-price-field > :nth-child(3) #sw-price-field-gross').blur();
        cy.wait('@calculatePrice').its('response.statusCode').should('equal', 200);
        cy.get('.sw-list-price-field > :nth-child(3) #sw-price-field-net').should('have.value', '16.798319327731');

        // General > Deliverability
        cy.get('input[name=sw-field--product-stock]').typeAndCheck('100');
        cy.get('input[name=sw-field--product-is-closeout]').click();
        //const delivery = Cypress.env('locale') === 'en-GB' ? '2-5 days' : '2-5 Tage';
        cy.get('#deliveryTimeId').typeSingleSelectAndCheck('2-5 Tage','#deliveryTimeId');
        cy.get('#sw-field--product-restock-time').typeAndCheck('10');
        cy.get('.sw-product-deliverability__shipping-free').click();
        cy.get('.sw-product-deliverability__min-purchase [type]').typeAndCheck('1');
        cy.get('.sw-product-deliverability__purchase-step [type]').typeAndCheck('1')
        cy.get('.sw-product-deliverability__max-purchase [type]').typeAndCheck('5')

        // General > Visibility & structure
        cy.get('.sw-product-detail__select-visibility').scrollIntoView();
        //const saleschannel = Cypress.env('testDataUsage') ? 'Footwear' : 'E2E install test';
        cy.get('.sw-product-detail__select-visibility').typeMultiSelectAndCheck('Footwear');
        cy.get('.sw-product-detail__select-visibility .sw-select-selection-list__input').type('{esc}');
        cy.get('.sw-category-tree__input-field').type('Angebote');
        cy.get('.sw-category-tree-field__search-results').contains('Angebote').click();
        cy.get('.sw-container.sw-product-feature-set-form__description .sw-inherit-wrapper__inheritance-label').click();

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        //const save = Cypress.env('locale') === 'en-GB' ? 'Save' : 'Speichern';
        cy.get(page.elements.productSaveAction).contains('Speichern');

        // Check from product listing
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Product created before update');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Product created before update');
        cy.get('.sw-data-grid__skeleton').should('not.exist');
    });

    it('@base @catalogue: should add product to cart', ()=>{
        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('markus.stein@test.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();
        cy.visit('/');

        // Search product
        const page = new CheckoutPageObject();
        let productName = 'Product created before update'
        cy.get('.header-search-input').should('be.visible').type(productName);
        cy.contains('.search-suggest-product-name', productName).click();
        cy.get('.product-detail-buy .btn-buy').click();
        cy.get('.modal-backdrop').click();
        cy.get('.header-cart > .btn').click();

        // Off canvas
        cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${page.elements.cartItem}-label`).contains(productName);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.get('.cart-item-details-container [title]').contains(productName);
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('14,99');
    });
});
