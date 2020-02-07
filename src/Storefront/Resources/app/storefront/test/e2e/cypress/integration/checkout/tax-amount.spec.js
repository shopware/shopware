import CheckoutPageObject from '../../support/pages/checkout.page-object';
import AccountPageObject from '../../support/pages/account.page-object';

const additionalProducts = [{
    name: '19% Product',
    taxName: '19%',
    productNumber: 'RS-1919',
}, {
    name: '7% Product',
    taxName: '7%',
    productNumber: 'RS-777',
}, {
    name: 'Mixed Products',
    taxName: '7%',
    productNumber: 'RS-719719',
}];
let product = {};

describe('Checkout: Use different taxes in products while checkout', () => {

    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            return cy.createCustomerFixtureStorefront()
        }).then(() => {
            cy.visit('/');
        })
    });

    additionalProducts.forEach(additionalProduct => {
        const contextDescription = additionalProduct.productNumber === "RS-777" ?
            `tax ${additionalProduct.taxName}, 2x same product` : `taxes: ${additionalProduct.taxName} & 19%`;

        context(`Checkout with ${additionalProduct.name} (${contextDescription})`, () => {
            beforeEach(() => {
                return cy.createProductFixture(additionalProduct).then(() => {
                    cy.visit('/');
                })
            });

            it('@package @checkout: Run checkout', () => {
                const page = new CheckoutPageObject();
                const accountPage = new AccountPageObject();
                let productName = product.name;

                const taxSum = additionalProduct.name === '7% Product' ? 0.65 : 1.60;
                const additionalTaxSum = additionalProduct.name === 'Mixed Products' ? 0.65 : taxSum;

                if (additionalProduct.productNumber === "RS-777") {
                    productName = additionalProduct.name;
                }

                // Product detail - first product
                cy.get('.header-search-input')
                    .should('be.visible')
                    .type(product.name);
                cy.contains('.search-suggest-product-name', productName).click();
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
                cy.get(`${page.elements.cartItem}-label`).contains(productName);
                cy.get(`${page.elements.offCanvasCart} .offcanvas-close`).click();

                // Product detail - Second product
                cy.get('.header-search-input')
                    .should('be.visible')
                    .type(additionalProduct.name);
                cy.contains('.search-suggest-product-name', additionalProduct.name).click();
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
                cy.get(`${page.elements.cartItem}-label`).contains(additionalProduct.name);

                // Checkout
                cy.get('.offcanvas-cart-actions .btn-primary').click();

                // Login
                cy.get('.checkout-main').should('be.visible');
                cy.get('.login-collapse-toggle').click();
                cy.get(accountPage.elements.loginCard).should('be.visible');
                cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
                cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

                // Confirm
                cy.get('.confirm-tos .card-title').contains('Terms, conditions and cancellation policy');
                cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
                cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
                cy.get('.confirm-address').contains('Pep Eroni');

                cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`)
                    .contains(additionalProduct.name);

                // We need to look at the calculation separately, for each test case
                if (additionalProduct.name === '7% Product') {
                    // 2x same products of 7% tax
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-tax-price')
                        .contains(`${taxSum * 2}`);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-total-price')
                        .contains(`${product.price[0].gross * 2}`);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${taxSum * 2}`);
                } else if (additionalProduct.name === 'Mixed Products') {
                    // 2 separate product of differing taxes (e.g. 19% and 7%)
                    cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`)
                        .contains(productName);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-tax-price')
                        .contains(taxSum);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-tax-price')
                        .contains(additionalTaxSum);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get('.checkout-aside-summary-value:nth-of-type(5)').contains(taxSum);
                    cy.get('.checkout-aside-summary-value:last-child').contains(additionalTaxSum);
                } else {
                    // 2 separate products of same tax (e.g. 19%)
                    cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`)
                        .contains(productName);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-tax-price')
                        .contains(taxSum);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-tax-price')
                        .contains(taxSum);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${taxSum * 2}`);
                }
                cy.get('.checkout-aside-summary-total').contains(`${product.price[0].gross * 2}`);

                // Finish checkout
                cy.get('#confirmFormSubmit').scrollIntoView();
                cy.get('#confirmFormSubmit').click();
                cy.get('.finish-header').contains('Thank you for your order with Demostore!');

                // Let's check the calculation on /finish as well
                cy.contains(additionalProduct.name);

                if (additionalProduct.name === '7% Product') {
                    // 2x same products of 7% tax
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-tax-price')
                        .contains(`${taxSum * 2}`);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-total-price')
                        .contains(`${product.price[0].gross * 2}`);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${taxSum * 2}`);
                } else if (additionalProduct.name === 'Mixed Products') {
                    // 2 separate product of differing taxes (e.g. 19% and 7%)
                    cy.contains(productName);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-tax-price')
                        .contains(additionalTaxSum);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-tax-price')
                        .contains(taxSum);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get('.checkout-aside-summary-value:nth-of-type(5)').contains(taxSum);
                    cy.get('.checkout-aside-summary-value:last-child').contains(additionalTaxSum);
                } else {
                    // 2 separate products of same tax (e.g. 19%)
                    cy.contains(productName);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-tax-price')
                        .contains(taxSum);
                    cy.get(':nth-child(2) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-tax-price')
                        .contains(taxSum);
                    cy.get(':nth-child(3) > :nth-child(1) > .cart-item-total-price')
                        .contains(product.price[0].gross);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${taxSum * 2}`);
                }
            });
        });
    });
});
