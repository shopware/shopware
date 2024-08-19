import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';

const additionalProducts = [
    {
        name: '19% Product',
        taxName: 'Standard rate',
        productNumber: 'RS-1919',
        price: [
            {
                'currencyId': 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'net': 8.40,
                'linked': false,
                'gross': 10,
            },
        ],
    },
    {
        name: '7% Product',
        taxName: 'Reduced rate',
        productNumber: 'RS-777',
        'price': [
            {
                'currencyId': 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'net': 8.40,
                'linked': false,
                'gross': 10,
            },
        ],
    },
    {
        name: 'Mixed Products',
        taxName: 'Reduced rate',
        productNumber: 'RS-719719',
        'price': [
            {
                'currencyId': 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'net': 8.40,
                'linked': false,
                'gross': 10,
            },
        ],
    },
];
let product = {};

/**
 * @package checkout
 */
describe('Checkout: Use different taxes in products while checkout', () => {

    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            return cy.createCustomerFixtureStorefront();
        }).then(() => {
            cy.visit('/');
        });
    });

    additionalProducts.forEach(additionalProduct => {
        const contextDescription = additionalProduct.productNumber === "RS-777" ?
            `tax ${additionalProduct.taxName}, 2x same product` : `taxes: ${additionalProduct.taxName} & 19%`;

        context(`Checkout with ${additionalProduct.name} (${contextDescription})`, () => {
            beforeEach(() => {
                return cy.createProductFixture(additionalProduct).then(() => {
                    cy.visit('/');
                });
            });

            it('@base @checkout @package: Run checkout', { tags: ['pa-checkout'] }, () => {
                const page = new CheckoutPageObject();
                const accountPage = new AccountPageObject();
                let productName = product.name;

                const taxSum = additionalProduct.name === '7% Product' ? 0.65 : 1.60;
                const additionalTaxSum = additionalProduct.name === 'Mixed Products' ? 0.65 : taxSum;

                const productTaxSum = 7.98;

                if (additionalProduct.productNumber === "RS-777") {
                    productName = additionalProduct.name;
                }

                // Product detail - first product
                cy.get('.header-search-input')
                    .should('be.visible')
                    .type(productName);
                cy.contains('.search-suggest-product-name', productName).click();
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(page.elements.offCanvasCart).should('be.visible');
                cy.get('.line-item-label').contains(productName);
                cy.get(`${page.elements.offCanvasCart} .offcanvas-close`).click();
                cy.get(page.elements.offCanvasCart).should('not.exist');

                // Product detail - Second product
                cy.get('.header-search-input')
                    .should('be.visible')
                    .type(additionalProduct.name, { force: true });
                cy.contains('.search-suggest-product-name', additionalProduct.name).click();
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(page.elements.offCanvasCart).should('be.visible');
                cy.get('.line-item-label').contains(additionalProduct.name);

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
                cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
                cy.get('.checkout-confirm-tos-label').scrollIntoView();
                cy.get('.checkout-confirm-tos-label').click(1, 1);
                cy.get('.confirm-address').contains('Pep Eroni');

                cy.get('.line-item-details-container .line-item-label')
                    .contains(additionalProduct.name);

                // We need to look at the calculation separately, for each test case
                if (additionalProduct.name === '7% Product') {
                    // 2x same products of 7% tax
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-tax-price`)
                        .contains(`${taxSum * 2}`);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-total-price`)
                        .contains(`${additionalProduct.price[0].gross * 2}`);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${taxSum * 2}`);
                    cy.get('.checkout-aside-summary-total').contains(`${additionalProduct.price[0].gross * 2}`);
                } else if (additionalProduct.name === 'Mixed Products') {
                    // 2 separate product of differing taxes (e.g. 19% and 7%)
                    cy.get('.line-item-details-container .line-item-label')
                        .contains(productName);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-tax-price`)
                        .contains(productTaxSum);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-total-price`)
                        .contains(product.price[0].gross);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-tax-price`)
                        .contains(additionalTaxSum);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-total-price`)
                        .contains(additionalProduct.price[0].gross);
                    cy.get('.checkout-aside-summary-value:nth-of-type(5)').contains(productTaxSum);
                    cy.get('.checkout-aside-summary-value:last-child').contains(additionalTaxSum);
                    cy.get('.checkout-aside-summary-total').contains(`${product.price[0].gross + additionalProduct.price[0].gross}`);
                } else {
                    // 2 separate products of same tax (e.g. 19%)
                    cy.get('.line-item-details-container .line-item-label')
                        .contains(productName);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-tax-price`)
                        .contains(productTaxSum);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-total-price`)
                        .contains(product.price[0].gross);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-tax-price`)
                        .contains(taxSum);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-total-price`)
                        .contains(additionalProduct.price[0].gross);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${productTaxSum + taxSum}`);
                    cy.get('.checkout-aside-summary-total').contains(`${product.price[0].gross + additionalProduct.price[0].gross}`);
                }

                // Finish checkout
                cy.get('#confirmFormSubmit').scrollIntoView();
                cy.get('#confirmFormSubmit').click();
                cy.get('.finish-header').contains('Thank you for your order with Demostore!');

                // Let's check the calculation on /finish as well
                cy.contains(additionalProduct.name);

                if (additionalProduct.name === '7% Product') {
                    // 2x same products of 7% tax
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-tax-price`)
                        .contains(`${taxSum * 2}`);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-total-price`)
                        .contains(`${additionalProduct.price[0].gross * 2}`);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${taxSum * 2}`);
                } else if (additionalProduct.name === 'Mixed Products') {
                    // 2 separate product of differing taxes (e.g. 19% and 7%)
                    cy.contains(productName);

                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-tax-price`)
                        .contains(productTaxSum);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-total-price`)
                        .contains(product.price[0].gross);

                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-tax-price`)
                        .contains(additionalTaxSum);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-total-price`)
                        .contains(additionalProduct.price[0].gross);
                    cy.get('.checkout-aside-summary-value:nth-of-type(5)').contains(productTaxSum);
                    cy.get('.checkout-aside-summary-value:last-child').contains(additionalTaxSum);
                } else {
                    // 2 separate products of same tax (e.g. 19%)
                    cy.contains(productName);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-tax-price`)
                        .contains(productTaxSum);
                    cy.get(`:nth-child(2) > :nth-child(1) > .line-item-total-price`)
                        .contains(product.price[0].gross);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-tax-price`)
                        .contains(taxSum);
                    cy.get(`:nth-child(3) > :nth-child(1) > .line-item-total-price`)
                        .contains(additionalProduct.price[0].gross);
                    cy.get('.checkout-aside-summary-value:last-child').contains(`${productTaxSum + taxSum}`);
                }
            });
        });
    });
});
