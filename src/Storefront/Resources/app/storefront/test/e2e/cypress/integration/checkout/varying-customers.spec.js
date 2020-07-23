import CheckoutPageObject from '../../support/pages/checkout.page-object';
import AccountPageObject from '../../support/pages/account.page-object';

const customers = [{
    firstName: 'Net',
    lastName: 'Customer',
    email: "tester@example.com",
    displayGross: false
}, {
    firstName: 'Gross',
    lastName: 'Customer',
    email: "tester@example.com",
    displayGross: true
}, {
    firstName: 'Differing',
    lastName: 'Addresses',
    email: "tester@example.com",
    displayGross: true
}];

let product = {};

describe('Checkout: Proceed checkout using various customers', () => {

    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
        });
    });

    customers.forEach(customer => {
        context(`Checkout with ${customer.firstName}  ${customer.lastName}`, () => {
            beforeEach(() => {
                return cy.createCustomerFixtureStorefront(customer).then(() => {
                    if (customer.displayGross) {
                        return null;
                    }

                    return cy.setCustomerGroup('RS-1232123', {
                        name: 'Net customergroup',
                        displayGross: false
                    })
                }).then(() => {
                    cy.visit('/account/login');
                });
            });

            it('@base @checkout: run checkout', () => {
                const page = new CheckoutPageObject();
                const accountPage = new AccountPageObject();
                const price = customer.displayGross ? product.price[0].gross : product.price[0].net;
                const vatSnippet = customer.displayGross ? 'incl. VAT' : 'excl. VAT';

                // Login
                cy.get(accountPage.elements.loginCard).should('be.visible');
                cy.get('#loginMail').typeAndCheckStorefront('tester@example.com');
                cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

                // Add new address and choose is as default shipping if necessary
                if (customer.firstName === 'Differing') {
                    // Add address form
                    cy.get('.account-content .account-aside-item[title="Addresses"]')
                        .should('be.visible')
                        .click();
                    cy.get('a[href="/account/address/create"]').click();
                    cy.get('.account-address-form').should('be.visible');

                    // Fill in and submit address
                    cy.get('#addresspersonalSalutation').typeAndSelect('Mr.');
                    cy.get('#addresspersonalFirstName').typeAndCheckStorefront('P.');
                    cy.get('#addresspersonalLastName').typeAndCheckStorefront('Sherman');
                    cy.get('#addressAddressStreet').typeAndCheckStorefront('42 Wallaby Way');
                    cy.get('#addressAddressZipcode').typeAndCheckStorefront('2000');
                    cy.get('#addressAddressCity').typeAndCheckStorefront('Sydney');
                    cy.get('#addressAddressCountry').typeAndSelect('Australia');
                    cy.get('.address-form-submit').click();
                    cy.get('.alert-success .alert-content').contains('Address has been saved.');

                    // Set new address as shipping address
                    cy.contains('Set as default shipping').click();
                    cy.get('.shipping-address p').contains('Sherman');
                }

                // Product detail
                cy.get('.header-search-input')
                    .should('be.visible')
                    .type(product.name);
                cy.get('.search-suggest-product-name').contains(product.name);
                cy.get('.search-suggest-product-price').contains(price);
                cy.get('.search-suggest-product-name').click();
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
                cy.get(`${page.elements.cartItem}-label`).contains(product.name);
                cy.get('.cart-item-price').contains(price);
                cy.get('.summary-value.summary-total').contains(price);

                // Checkout
                cy.get('.offcanvas-cart-actions .btn-primary').click();
                cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
                cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
                cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
                cy.get('.confirm-address').contains('Pep Eroni');
                cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`).contains(product.name);
                cy.get(`${page.elements.cartItem}-total-price`).contains(price);
                cy.get('.col-5.checkout-aside-summary-value').contains(price);
                cy.get('.cart-header-tax-price').contains(vatSnippet);

                // Check differing address if necessary
                if (customer.firstName === 'Differing') {
                    cy.contains('Same as billing address').should('not.exist');
                }

                // Finish checkout
                cy.get('#confirmFormSubmit').scrollIntoView();
                cy.get('#confirmFormSubmit').click();
                cy.get('.finish-header').contains('Thank you for your order with Demostore!');
                cy.get('.checkout-aside-summary-total').contains('10.00');
                cy.get('.col-5.checkout-aside-summary-value').contains(price);

                // Check further things on /finish:
                if (customer.firstName === 'Differing') {
                    // if address is differing
                    cy.get('.finish-address-billing').contains('Pep Eroni');
                    cy.get('.finish-address-shipping').contains('Sherman');
                }
                cy.get('.cart-header-tax-price').contains(vatSnippet);
                cy.get('.col-12.cart-item-tax-price').contains('1.60');
                cy.get('.col-5.checkout-aside-summary-value').contains('1.60');
            });
        });
    });
});
