/// <reference types="Cypress" />

describe('Admin & Storefront: private customer registration by using product created via UI', () => {

    before(() => {
        cy.setToInitialState();
    });

    beforeEach(() => {
        cy.loginViaApi();
    });

    it('@package: add initial settings', ()=>{

        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.cart.showCustomerComment': true,
                        'core.cart.showDeliveryTime': true
                    }
                }
            };
            return cy.request(requestConfig);
        });
        cy.visit(`${Cypress.env('admin')}#/sw/settings/listing/index`);
        cy.url().should('include', 'settings/listing/index');
        cy.setSalesChannel('E2E install test');
        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        cy.url().should('include', 'settings/shipping/index');
        cy.setShippingMethod('Standard', '5', '4');
        cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        cy.url().should('include', 'settings/payment/index');
        cy.setPaymentMethod('Cash on delivery');
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.url().should('include', 'dashboard/index');
        cy.goToSalesChannelDetail('E2E install test')
            .selectPaymentMethodForSalesChannel('Cash on delivery')
            .selectShippingMethodForSalesChannel('Standard');

    });

    it('@package: add product via UI', ()=>{

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('createProduct');
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'post'
        }).as('calculatePrice');

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.url().should('include', 'product/index');

        // General > General information
        cy.get('.sw-button.sw-button--primary').click();
        cy.get('#sw-field--product-name').typeAndCheck('Product-5');
        cy.get('#manufacturerId').typeSingleSelectAndCheck('shopware AG', '#manufacturerId');
        cy.get('.sw-text-editor__content-editor').type('Test');
        cy.get('.sw-product-basic-form__promotion-switch [type]').check();

        // General > Prices
        cy.get('select#sw-field--product-taxId').select('Standaard tarief');
        cy.get('.sw-list-price-field__price .sw-price-field__gross [type]').typeAndCheck('14.99');
        cy.get('.sw-list-price-field__price .sw-price-field__net [type]').focus().blur();
        cy.get('.sw-list-price-field__price .sw-price-field__net [type]').should('have.value', '12.596638655462');
        cy.wait('@calculatePrice').its('response.statusCode').should('equal', 200);

        // General > Deliverability
        cy.get('input#sw-field--product-stock').typeAndCheck('100');
        cy.get('#deliveryTimeId').typeSingleSelectAndCheck('1-3 days', '#deliveryTimeId');
        cy.get('input#sw-field--product-restock-time').typeAndCheck('10');
        cy.get('.sw-product-deliverability__min-purchase [type]').typeAndCheck('1');
        cy.get('.sw-product-deliverability__purchase-step [type]').typeAndCheck('1')
        cy.get('.sw-product-deliverability__max-purchase [type]').typeAndCheck('10')

        // General > Visibility & structure
        cy.get('.sw-product-detail__select-visibility').scrollIntoView();
        cy.contains('.sw-product-detail__select-visibility', 'E2E install test');
        cy.get('.sw-product-category-form [type="checkbox"]').should('be.checked');

        // Save product
        cy.get('.sw-button-process__content').click();
        cy.wait('@createProduct').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan');

        // Check from product listing
        cy.get('a.smart-bar__back-btn').click();
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Product-5');
        cy.get(`.sw-data-grid__row--0 .sw-data-grid__cell--name`).contains('Product-5');
        cy.get('.sw-data-grid__skeleton').should('not.exist');

    });

    it('@package: register as private customer and complete shopping', ()=>{

        cy.intercept({
            url: `/account/register`,
            method: 'POST'
        }).as('registerCustomer');

        cy.visit('/account/login');
        cy.url().should('include', '/account/login');
        cy.get('select#personalSalutation').select('Mr.');
        cy.get('input#personalFirstName').clear().type('Test');
        cy.get('input#personalLastName').clear().type('Tester');
        cy.get('input#personalMail').clear().type('test5@tester.com');
        cy.get('input#personalPassword').clear().type('shopware');
        cy.get('input#billingAddressAddressStreet').clear().type('Test street');
        cy.get('input#billingAddressAddressZipcode').clear().type('12345');
        cy.get('input#billingAddressAddressCity').clear().type('Test city');
        cy.get('select#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Search product
        cy.get('.header-search-input').should('be.visible').type('Product-5');
        cy.contains('.search-suggest-product-name', 'Product-5').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Product-5');

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.get('.cart-item-details-container [title]').contains('Product-5');
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('14,99');
        cy.get('.cart-item-delivery-date').should('be.visible');

        // Total: product price + shipping
        cy.get('.col-5.checkout-aside-summary-total').contains('19,99');
        cy.get('a[title="Proceed to checkout"]').click();

        // Confirm
        cy.get('.confirm-address').contains('Test Tester');
        cy.get('.cart-item-label').contains('Product-5');
        cy.get('.cart-item-total-price').scrollIntoView();
        cy.get('.cart-item-total-price').contains('14,99');
        cy.get('.col-5.checkout-aside-summary-total').contains('19,99');
        cy.get('.cart-item-delivery-date').should('be.visible');
        cy.get('.checkout-customer-comment-control').should('be.visible');

        // Finish checkout
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains(`Thank you for your order with E2E install test!`);

    });
    it('@package: check order in admin', ()=>{

        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Test Tester');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--orderCustomer-firstName').contains('Tester, Test');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--amountTotal').contains('19,99');
        cy.get('.sw-data-grid__skeleton').should('not.exist');
    });

});
