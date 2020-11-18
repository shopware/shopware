import CheckoutPageObject from "../../support/pages/checkout.page-object";

let product = {};

describe('Test payment and shipping methods selection', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createProductFixture()
                    .then((result) => {
                        product = result;
                        return cy.createCustomerFixtureStorefront();
                    })
                    .then(() => {
                        cy.visit('/account/login');

                        // Login
                        cy.get('.login-card').should('be.visible');
                        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
                        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                        cy.get('.login-submit [type="submit"]').click();

                        cy.visit('/');
                    });
            });
    });

    it('@base @checkout: should show methods', () => {
        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

        cy.get(`${page.elements.paymentFormConfirm}`).should('be.visible');
        cy.get(`${page.elements.shippingFormConfirm}`).should('be.visible');

        cy.get(`${page.elements.paymentMethodsContainer}`)
            .should('be.visible')
            .children()
            .should('have.length', 3);

        cy.get(`${page.elements.shippingMethodsContainer}`)
            .should('be.visible')
            .children()
            .should('have.length', 2);
    });

    it('@base @confirm: should have working collapse on multiple methods', () => {
        cy.createPaymentMethodFixture({ name: 'Test Method #1'})
            .then(() => {
                return cy.createPaymentMethodFixture({ name: 'Test Method #2'});
            })
            .then(() => {
                return cy.createPaymentMethodFixture({ name: 'Test Method #3'});
            })
            .then(() => {
                const page = new CheckoutPageObject();

                // add product to cart
                cy.get('.header-search-input')
                    .should('be.visible')
                    .type(product.name);
                cy.contains('.search-suggest-product-name', product.name).click();
                cy.get('.product-detail-buy .btn-buy').click();

                // Off canvas
                cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
                cy.get(`${page.elements.cartItem}-label`).contains(product.name);

                // Go to cart
                cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

                // check for correct collapsed state at page initialization
                cy.get(`${page.elements.paymentMethodsContainer}`)
                    .should('be.visible')
                    .children('.payment-method')
                    .should('have.length', 5);
                cy.get(`${page.elements.paymentMethodsCollapseContainer}`).should('exist');
                cy.get(`${page.elements.paymentMethodsCollapseContainer} > .payment-method`).should('not.be.visible');
                cy.get(`${page.elements.paymentMethodsCollapseTrigger}`)
                    .should('be.visible')
                    .should('contain', 'Show more');

                // click collapse trigger to show other payment methods
                cy.get(`${page.elements.paymentMethodsCollapseTrigger}`).click();
                cy.get(`${page.elements.paymentMethodsCollapseContainer} > .payment-method`).should('be.visible');
                cy.get(`${page.elements.paymentMethodsCollapseTrigger}`).should('contain', 'Show less');

                // click it again to collapse methods again
                cy.get(`${page.elements.paymentMethodsCollapseTrigger}`).click();
                cy.get(`${page.elements.paymentMethodsCollapseContainer}`).should('exist'); // wait for collapse to finish transition
                cy.get(`${page.elements.paymentMethodsCollapseContainer} > .payment-method`).should('not.be.visible');
                cy.get(`${page.elements.paymentMethodsCollapseTrigger}`).should('contain', 'Show more');
        });
    });

    it('@base @confirm: should change payment and shipping methods', () => {
        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
        cy.get(`${page.elements.cartItem}-label`).contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);

        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(3) .payment-method-label`)
            .should('exist')
            .contains('Paid in advance');
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(3) .payment-method-label`).click(1, 1);

        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-label`)
            .contains('Express').click(1, 1)

        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();

        cy.get('.finish-header').contains('Thank you for your order with Demostore!');

        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(1)')
            .should('contain', 'Paid in advance');

        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(2)')
            .should('contain', 'Express');
    });
});
