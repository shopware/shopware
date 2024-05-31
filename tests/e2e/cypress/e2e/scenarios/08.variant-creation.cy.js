/// <reference types="Cypress" />
import ProductPageObject from '../../support/pages/module/sw-product.page-object';
import CheckoutPageObject from '../../support/pages/checkout.page-object';

const guestCustomer = require('../../fixtures/guest-customer.json');

describe('Create a variant product using default customer and buy it via cash on delivery.', { tags: ['pa-checkout'] }, () => {
    beforeEach(() => {
        cy.createPropertyFixture({
            options: [{name: 'Red'}, {name: 'Yellow'}, {name: 'Green'}],
        }).then(() => {
            return cy.createPropertyFixture({
                name: 'Size',
                options: [{name: 'S'}, {name: 'M'}],
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Test Product',
                productNumber: 'TEST-1234',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    linked: true,
                    gross: 10,
                }],
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it.skip('@package: should add multidimensional variant to product and set surcharge', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-visibility`,
            method: 'POST',
        }).as('saveProductVisibility');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/sales-channel`,
            method: 'POST',
        }).as('getSalesChannel');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('getCategoryDetail');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/property-group`,
            method: 'POST',
        }).as('getPropertyGroup');

        cy.intercept({
            url: `**/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        const page = new ProductPageObject();
        const checkoutPage = new CheckoutPageObject();
        const salesChannel = Cypress.env('storefrontName');

        // Add product to sales channel
        cy.url().should('include', 'dashboard/index');
        cy.contains(salesChannel).click();
        cy.url().should('include', 'sales/channel/detail');
        cy.get('.sw-tabs-item[title="Producten"]').click();
        cy.get('.sw-button.sw-button--ghost').click();
        cy.get('.sw-data-grid__body .sw-data-grid__cell--selection .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__bulk-selected-label').should('include.text', 'Geselecteerd');
        cy.get('.sw-button.sw-button--primary.sw-button--small').click();
        cy.wait('@saveProductVisibility').its('response.statusCode').should('equal', 204);

        cy.get('.sw-button-process.sw-sales-channel-detail__save-action').click();
        cy.wait('@getSalesChannel').its('response.statusCode').should('equal', 200);

        // Navigate to variant generator listing and start
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-product-list').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-product-detail__tab-variants').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-product-detail-variants__generated-variants-empty-state-button').click();

        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Size', [0, 1], 2);
        cy.get('.sw-product-variants__generate-action').should('be.visible');

        cy.get('.sw-product-variants__generate-action').click();
        cy.get('.sw-product-modal-variant-generation').should('be.visible');
        page.generateVariants('Color', [0, 1, 2], 6);
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
        cy.get('.sw-product-variants-overview').should('be.visible');

        // Get green variant
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-simple-search-field--form input').should('be.visible');
        cy.wait('@getPropertyGroup')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-simple-search-field--form input').clearTypeAndCheck('Green');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid__row--2').should('not.exist');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--name', 'Green');

        // Find variant to set surcharge on
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-price-preview').should('be.visible');
        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').dblclick({force: true});
        cy.get('.sw-data-grid__row--0 .sw-price-preview').should('not.exist');

        // Set surcharge and save
        cy.get('.is--inline-edit .sw-inheritance-switch').first().should('be.visible');
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().should('be.visible');
        cy.get('.is--inline-edit .sw-inheritance-switch').first().click();
        cy.get('.sw-data-grid__row--0 #sw-price-field-gross').first().clearTypeAndCheck('8');
        cy.get('button[title="Opslaan"] svg').click();
        cy.get('.sw-button-process.sw-product-detail__save-action').click();
        cy.url().should('include', 'product/detail');
        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content').should('be.visible');

        // Visit storefront and find the product
        cy.visit('/');

        cy.contains('Home');
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Test Product');
        cy.contains('.search-suggest-product-name', 'Test Product').click();

        // Add reduced price variant product (Green, S) to shopping cart
        cy.contains('Green').click({force: true});
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('label[title="S"]').click({force: true});
        cy.get('.product-detail-price').should('include.text', '8,00');
        cy.contains('Add to shopping cart').click();

        // Off canvas
        cy.get(checkoutPage.elements.offCanvasCart).should('be.visible');
        cy.contains('.line-item-label', 'Test Product');

        // Total: product price
        cy.contains('.col-5.summary-value', '8,00');
        cy.get('a[title="Go to checkout"]').should('be.visible').click();
        cy.url().should('include', '/checkout/register');

        // Guest check out
        cy.get('select#personalSalutation').typeAndSelect(guestCustomer.salutation);
        cy.get('input#personalFirstName').clearTypeAndCheck(guestCustomer.firstName);
        cy.get('input#personalLastName').clearTypeAndCheck(guestCustomer.lastName);
        cy.get('input#personalMail').clearTypeAndCheck(guestCustomer.email);
        cy.get("#personalGuest").check();
        cy.get('input#personalPassword').clearTypeAndCheck(guestCustomer.password);
        cy.get('input#billingAddressAddressStreet').clearTypeAndCheck(guestCustomer.street);
        cy.get('input#billingAddressAddressZipcode').clearTypeAndCheck(guestCustomer.zipCode);
        cy.get('input#billingAddressAddressCity').clearTypeAndCheck(guestCustomer.city);
        cy.get('select#billingAddressAddressCountry').typeAndSelect(guestCustomer.country);
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);

        // Verify the variant price
        cy.contains('.confirm-address', 'Wolf Kurt');
        cy.contains('.line-item-label', 'Test Product');
        cy.get('.line-item-total-price').scrollIntoView().contains('8,00');
        cy.contains('.col-5.checkout-aside-summary-total', '8,00');

        // Finish checkout
        cy.contains('.confirm-tos .card-title', 'Terms and conditions and cancellation policy');
        cy.get('.confirm-tos .form-check label').scrollIntoView();
        cy.get('.confirm-tos .form-check label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView().click();
        cy.contains('.finish-header', `Thank you for your order with E2E install test!`);

        // Verify the order from the storefront
        cy.visit('/account/login');
        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
        cy.contains('.account-overview-profile > .card > .card-body', 'wolf@kurt.com');
        cy.get('.order-table-header-heading').should('be.visible')
            .and('include.text', 'Order');
    });
});
