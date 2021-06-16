import CheckoutPageObject from "../../support/pages/checkout.page-object";
import AccountPageObject from "../../support/pages/account.page-object";
import createId from 'uuid/v4';

let product = {};
const blockedSnippet = 'The shipping method "Standard" is blocked for your current shopping cart.';

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
                            value: {paymentMethodIds: [paymentMethodId]}
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

    it('@cart @payment @shipping: Check rule conditions in cart', () => {
        // Scenario: The shipping method "Standard" has a custom availability rule. This rules only allows "Standard"
        // shipping, if the payment method "invoice" is selected.

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

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();

        // Cart page
        cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

        // Collapse shipping costs menu
        cy.get('.cart-shipping-costs-container .cart-shipping-costs-btn').click();
        cy.get('#collapseShippingCost').should('be.visible');
        // Select the payment method invoice
        cy.get('#collapseShippingCost select[id="paymentMethodId"]').select('Invoice');
        // Page is reloading
        // Waits until the page is reloaded, since the shipping menu is collapsed again
        cy.get('#shippingMethodId').should('not.be.visible');
        cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

        // Collapse shipping costs menu
        cy.get('.cart-shipping-costs-container .cart-shipping-costs-btn').click();
        cy.get('#collapseShippingCost').should('be.visible');
        // Select the shipping method "Standard"
        cy.get('#collapseShippingCost select[id="shippingMethodId"]').select('Standard');
        // Page is reloading
        // Waits until the page is reloaded, since the shipping menu is collapsed again
        cy.get('#shippingMethodId').should('not.be.visible');
        cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

        // Check if we don't have a warning message anymore
        // The alert should be gone by now and the element isn't visible cause it's empty
        cy.get('.checkout-main .flashbags').should('not.be.visible');

        // Now switch the payment method and verify that "Standard" shipping is blocked
        // Collapse shipping costs menu
        cy.get('.cart-shipping-costs-container .cart-shipping-costs-btn').click();
        cy.get('#collapseShippingCost').should('be.visible');
        // Select the payment method invoice
        cy.get('#collapseShippingCost select[id="paymentMethodId"]').select('Cash on delivery');
        // Page is reloading

        cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

        // Check if we're getting a message that the current shipping method is not available
        cy.get('.alert-content-container .alert-content')
            .contains(blockedSnippet);

        // Also check that message in the offcanvas cart
        cy.get('.header-cart').click();
        // Check if we're getting a message in the offcanvas cart
        cy.get(`${page.elements.offCanvasCart} .alert-warning .alert-content`)
            .contains(blockedSnippet);
    });

    it('@checkout @payment @shipping: Check rule conditions in checkout', () => {
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
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Change payment to "Invoice"
        cy.get(`${page.elements.paymentMethodsContainer} .payment-method-label`)
            .should('exist')
            .contains('Invoice')
            .click(1, 1);

        // Page should reload
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Change shipping method to "Standard"
        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-label`)
            .should('exist')
            .contains('Standard')
            .click(1, 1);

        // Page should reload
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Reload the page to prevent flakiness with the flashbacks
        cy.reload();

        // There should be no alert
        cy.contains(blockedSnippet).should('not.exist');

        // Confirm TOS checkbox
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);

        // Change payment to Cash on delivery
        cy.get(`${page.elements.paymentMethodsContainer} .payment-method-label`)
            .should('exist')
            .contains('Cash on delivery')
            .click(1, 1);

        // Page should reload
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');

        // Validate that the shipping method is not available
        cy.get('.alert  .alert-content')
            .contains(blockedSnippet);

        // Check if the label is marked as invalid
        cy.get('#confirmFormSubmit').should('be.disabled');
    });
});
