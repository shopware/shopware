import CheckoutPageObject from "../../support/pages/checkout.page-object";
import AccountPageObject from "../../support/pages/account.page-object";
import createId from 'uuid/v4';

let product = {};

describe('Checkout rule builder handling for shipping and payment methods', () => {
    beforeEach(() => {
        cy.searchViaAdminApi({
            endpoint: 'payment-method',
            data: {
                field: 'name',
                value: 'Invoice'
            }
        }).then(({id: paymentMethodId}) => {
            const ruleId = createId().replace(/-/g, '');
            const orContainerId = createId().replace(/-/g, '');
            const andContainerId = createId().replace(/-/g, '');
            const paymentMethodRuleId = createId().replace(/-/g, '');

            return cy.createRuleFixture({
                id: ruleId,
                conditions: [{
                    id: orContainerId,
                    ruleId: ruleId,
                    children: [{
                        id: andContainerId,
                        ruleId: ruleId,
                        parentId: orContainerId,
                        children: [{
                            ruleId: ruleId,
                            parentId: andContainerId,
                            id: paymentMethodRuleId,
                            value: { paymentMethodIds: [paymentMethodId] }
                        }]
                    }]
                }]
            });
        }).then(() => {
            return cy.createProductFixture().then(() => {
                return cy.createDefaultFixture('category')
            });
        }).then(() => {
            return cy.fixture('product');
        }).then((productFixture) => {
            product = productFixture;
            return cy.createCustomerFixtureStorefront();
        }).then(() => {
            cy.visit('/');
        });
    });

    it.skip('@cart @payment @shipping: Check rule conditions in cart', () => {
        const page = new CheckoutPageObject();

        // Product detail
        cy.get('.header-search-input').should('be.visible');
        cy.get('.header-search-input').type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-price').contains(product.price[0].gross);
        cy.get('.search-suggest-product-name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Check if we're getting a message in the offcanvas cart
        cy.get(`${page.elements.offCanvasCart} .alert-danger .alert-content`)
            .contains('The shipping method Standard is blocked for your current shopping cart.');

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();

        // Cart page
        cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

        // Check if we're getting a message that the current shipping method is not available
        cy.get('.alert-content-container .alert-content')
            .contains('Shipping method Standard not available.');

        // Next open up the shipping calc precalucation and check what happens when we're switching to invoice as
        // payment method
        cy.get('.cart-shipping-costs-container .cart-shipping-costs-btn').click();
        cy.get('#collapseShippingCost').should('be.visible');

        cy.get('#collapseShippingCost select[name="paymentMethodId"]').select('Invoice');

        // Check if we don't have a warning message anymore
        cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

        // The alert should be gone by now and the element isn't visible cause it's empty
        cy.get('.checkout-main .flashbags').should('not.be.visible');
    });

    it.skip('@checkout @payment @shipping: Check rule conditions in checkout', () => {
        const accountPage = new AccountPageObject();
        const page = new CheckoutPageObject();

        // Product detail
        cy.get('.header-search-input').should('be.visible');
        cy.get('.header-search-input').type(product.name);
        cy.get('.search-suggest-product-name').contains(product.name);
        cy.get('.search-suggest-product-price').contains(product.price[0].gross);
        cy.get('.search-suggest-product-name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Check if we're getting a message in the offcanvas cart
        cy.get(`${page.elements.offCanvasCart} .alert-danger .alert-content`)
            .contains('The shipping method Standard is blocked for your current shopping cart.');

        // Go to checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();
        cy.get('.checkout-main').should('be.visible');

        // Login
        cy.get('.login-collapse-toggle').click();
        cy.get(accountPage.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${accountPage.elements.loginSubmit} [type="submit"]`).click();

        // Check if we're on the confirm page
        cy.get('.confirm-tos .card-title').contains('Terms, conditions and cancellation policy');

        // Flash message should be empty
        cy.get('.checkout-main .flashbags').should('not.be.visible');

        // Change payment to Cash on delivery
        cy.get('.confirm-payment .btn-light[data-target="#confirmPaymentModal"]').click();
        cy.get('.modal-title.h5').contains('Change payment');
        cy.get('.payment-method-label[for="paymentMethod1"]').click();
        cy.get('form[name=confirmPaymentForm] .btn-primary').click();

        // Page should reload
        cy.get('.confirm-tos .card-title').contains('Terms, conditions and cancellation policy');

        // Validate that the shipping method is not available
        cy.get('.alert-content-container .alert-content')
            .contains('Shipping method Standard not available.');

        // Check if the label is marked as invalid
        cy.get('.confirm-shipping-current.is-invalid').contains('Standard');
        cy.get('.confirm-method-tooltip').should('be.visible');
    });
});
