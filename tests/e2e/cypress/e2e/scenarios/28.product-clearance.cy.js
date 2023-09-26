/// <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

describe('Hide products after clearance & free shipping.', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Test Product',
            productNumber: 'TEST-1234',
            price: [{
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                linked: true,
                gross: 10,
            }],
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@package: create a clearance product, make an order and verify from the storefront', { tags: ['pa-inventory'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('setProductVisibility');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('getSalesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('searchProduct');

        cy.intercept({
            url: `**/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        const page = new ProductPageObject();
        const checkoutPage = new CheckoutPageObject();
        const salesChannel = Cypress.env('storefrontName');

        // Add product to sales channel
        cy.contains(salesChannel).click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@setProductVisibility').its('response.statusCode').should('equal', 204);

        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@getSalesChannel').its('response.statusCode').should('equal', 200);

        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.url().should('include', 'product/detail');

        // set stock number as 1
        cy.get('#sw-field--product-stock').scrollIntoView();
        cy.get('input#sw-field--product-stock').clearTypeAndCheck('1');

        cy.get('input[name="sw-field--product-is-closeout"]').click();
        cy.get('.sw-product-deliverability__shipping-free [type]').click();

        cy.get(page.elements.productSaveAction).click();
        cy.wait('@searchProduct').its('response.statusCode').should('equal', 200);

        // add product to cart
        cy.visit('/');

        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.contains('.delivery-information', 'Free shipping');
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');

        // Guest check out
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();
        cy.url().should('include', '/checkout/register');
        cy.get('select#personalSalutation').typeAndSelect('Mr.');
        cy.get('input#personalFirstName').clearTypeAndCheck('Wolf');
        cy.get('input#personalLastName').clearTypeAndCheck('Kurt');
        cy.get('input#personalMail').clearTypeAndCheck('wolf@kurt.com');
        cy.get("#personalGuest").check();
        cy.get('input#personalPassword').clearTypeAndCheck('shopware');
        cy.get('input#billingAddressAddressStreet').clearTypeAndCheck('Test street');
        cy.get('input#billingAddressAddressZipcode').clearTypeAndCheck('48500');
        cy.get('input#billingAddressAddressCity').clearTypeAndCheck('Amsterdam');
        cy.get('select#billingAddressAddressCountry').typeAndSelect('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Go to cart
        cy.get('.confirm-tos .form-check label').scrollIntoView();
        cy.get('.confirm-tos .form-check label').click(1, 1);
        cy.contains('.line-item-label', 'Test Product');
        cy.get('#confirmFormSubmit').scrollIntoView().click();
        cy.contains('.finish-header', `Thank you for your order with E2E install test!`);

        // after purchase verify 'add to shopping cart' button is not available
        cy.visit('/');
        cy.get('.header-search-input').should('be.visible').type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();
        cy.get('.product-detail-buy .btn-buy').should('not.exist');
        cy.contains('No longer available').should('exist');
    });

    it('@package: should NOT show a product at the store front after changing settings', { tags: ['pa-inventory'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.listing.hideCloseoutProductsWhenOutOfStock': true,
                    },
                },
            };
            return cy.request(requestConfig);
        });

        // verify no product is available at the storefront
        cy.visit('/');
        cy.contains('No products found.').should('exist');
    });
});
