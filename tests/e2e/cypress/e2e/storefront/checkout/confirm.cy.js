import CheckoutPageObject from '../../../support/pages/checkout.page-object';

let product = {};

/**
 * @package checkout
 */
describe('Test payment and shipping methods selection', () => {
    beforeEach(() => {
        cy.createProductFixture()
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

    it('@base @checkout: should show methods', { tags: ['pa-checkout'] }, () => {
        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

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

    it('@base @confirm: should have working collapse on multiple methods', { tags: ['pa-checkout'] }, () => {
        cy.createPaymentMethodFixture({ name: 'Test Method #1', technicalName: 'payment_test_1' })
            .then(() => {
                return cy.createPaymentMethodFixture({ name: 'Test Method #2', technicalName: 'payment_test_2'});
            })
            .then(() => {
                return cy.createPaymentMethodFixture({ name: 'Test Method #3', technicalName: 'payment_test_3'});
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
                cy.get(page.elements.offCanvasCart).should('be.visible');
                cy.get('.line-item-label').contains(product.name);

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

    it('@base @confirm @package: should change payment and shipping methods', { tags: ['pa-checkout'] }, () => {
        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

        cy.get('.checkout-confirm-tos-label').scrollIntoView();
        cy.get('.checkout-confirm-tos-label').click(1, 1);

        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(3) .payment-method-label`)
            .should('exist')
            .contains('Paid in advance');
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(3) .payment-method-label`).click(1, 1);

        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-label`)
            .contains('Express').click(1, 1);

        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();

        cy.get('.finish-header').contains('Thank you for your order with Demostore!');

        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(1)')
            .should('contain', 'Paid in advance');

        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(2)')
            .should('contain', 'Express');

        cy.go('back');

        cy.get('.account-welcome h1').contains('Orders');
    });

    it('@base @confirm @package: should repeat the order with different payment method', { tags: ['pa-checkout'] }, () => {
        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();
        cy.get('.checkout-confirm-tos-label').scrollIntoView();
        cy.get('.checkout-confirm-tos-label').click(1, 1);

        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(1) .payment-method-label`)
            .should('exist')
            .contains('Invoice');
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(1) .payment-method-label`).click(1, 1);
        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-label`)
            .contains('Standard').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(1)')
            .should('contain', 'Invoice');
        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(2)')
            .should('contain', 'Standard');

        // repeat the order with changing payment method
        cy.visit('/account/order');
        cy.url().should('include', 'account/order');
        cy.get('#accountOrderDropdown').click();
        cy.contains('Repeat order').click();

        // Off canvas
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();
        cy.get('.checkout-confirm-tos-label').scrollIntoView();
        cy.get('.checkout-confirm-tos-label').click(1, 1);
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(3) .payment-method-label`)
            .should('exist')
            .contains('Paid in advance');
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(3) .payment-method-label`).click(1, 1);
        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-label`)
            .contains('Express').click(1, 1);

        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(1)')
            .should('contain', 'Paid in advance');
        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(2)')
            .should('contain', 'Express');
    });

    it('@base @confirm @package: should cancel the order', { tags: ['pa-checkout'] }, () => {
        cy.getBearerAuth().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: 'api/_action/system-config/batch',
                body: {
                    null: {
                        'core.cart.enableOrderRefunds': true,
                    },
                },
            };
            return cy.request(requestConfig);
        });

        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();
        cy.get('.checkout-confirm-tos-label').scrollIntoView();
        cy.get('.checkout-confirm-tos-label').click(1, 1);
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(1) .payment-method-label`)
            .should('exist')
            .contains('Invoice');
        cy.get(`${page.elements.paymentMethodsContainer} > :nth-child(1) .payment-method-label`).click(1, 1);
        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-label`)
            .contains('Standard').click(1, 1);

        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(1)')
            .should('contain', 'Invoice');
        cy.get('.finish-order-details .checkout-card .card-body p:nth-of-type(2)')
            .should('contain', 'Standard');

        // cancel the order
        cy.visit('/account/order');
        cy.url().should('include', 'account/order');
        cy.get('#accountOrderDropdown').click();
        cy.contains('Cancel order').click();
        cy.get('[data-backdrop] .modal-title').should('be.visible');
        cy.get('[data-backdrop] .modal-body').should('include.text', 'Are you sure you want to cancel your order after all?');
        cy.get('[action] .btn-primary').click();
        cy.get('.order-item-status-badge-cancelled').should('be.visible').contains('Cancelled');
    });

    it.skip('@base @confirm: should have a working wishlist', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `**/wishlist/add/**`,
            method: 'POST',
        }).as('wishlistAdd');

        cy.intercept({
            url: `**/wishlist/remove/**`,
            method: 'POST',
        }).as('wishlistRemove');

        cy.getBearerAuth().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.cart.wishlistEnabled': true,
                    },
                },
            };

            return cy.request(requestConfig);
        });

        const page = new CheckoutPageObject();

        // add product to cart
        cy.get('.header-search-input')
            .should('be.visible')
            .type(product.name);
        cy.contains('.search-suggest-product-name', product.name).click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

        cy.get('.line-item .product-wishlist-action').scrollIntoView();
        cy.get('.line-item .product-wishlist-action.product-wishlist-not-added')
            .should('be.visible')
            .contains('Add to wishlist');

        cy.get('.line-item .product-wishlist-action')
            .should('be.visible')
            .click();

        cy.wait('@wishlistAdd').its('response.statusCode').should('equal', 200);

        cy.get('.line-item .product-wishlist-action.product-wishlist-added')
            .should('be.visible')
            .contains('Remove from wishlist');

        cy.get('.line-item .product-wishlist-action').click();

        cy.wait('@wishlistRemove').its('response.statusCode').should('equal', 200);

        cy.get('.line-item .product-wishlist-action.product-wishlist-not-added')
            .should('be.visible')
            .contains('Add to wishlist');

        cy.get('.line-item .product-wishlist-action').click();

        cy.wait('@wishlistAdd').its('response.statusCode').should('equal', 200);

        cy.visit('/wishlist');

        cy.get('.product-name').contains(product.name);
    });

    it('@base @confirm: should have correct order of shipping methods', { tags: ['pa-checkout'] }, () => {
        cy.window().then((win) => {
            const salesChannels = [
                { id: win.salesChannelId },
            ];

            const SHIPPING_METHOD_STANDARD = 'Standard';
            const SHIPPING_METHOD_EXPRESS = 'Express';

            let defaultShippingMethodId = null;
            let initialShippingMethods = {};

            /**
             * since the assignment of the default shipping method for the sales-channel
             * in the migration is not deterministic. so, we first need to find out what
             * the state is in order to build the correct assertions
             */
            cy.searchViaAdminApi({
                endpoint: 'sales-channel',
                data: {
                    field: 'name',
                    value: 'Storefront',
                },
            }).then((salesChannel) => {
                defaultShippingMethodId = salesChannel.attributes.shippingMethodId;

                return cy.searchViaAdminApi({
                    endpoint: 'shipping-method',
                    data: {
                        field: 'name',
                        value: SHIPPING_METHOD_STANDARD,
                    },
                });
            }).then((standardShippingMethod) => {
                const {id} = standardShippingMethod;

                initialShippingMethods[id] = SHIPPING_METHOD_STANDARD;

                return cy.searchViaAdminApi({
                    endpoint: 'shipping-method',
                    data: {
                        field: 'name',
                        value: SHIPPING_METHOD_EXPRESS,
                    },
                });
            }).then((expressShippingMethod) => {
                const {id} = expressShippingMethod;

                initialShippingMethods[id] = SHIPPING_METHOD_EXPRESS;
                initialShippingMethods[id] = SHIPPING_METHOD_EXPRESS;

                cy.createShippingFixture({ name: 'Test Method #1', technicalName: 'shipping_test_1', position: -1, salesChannels})
                    .then(() => {
                        return cy.createShippingFixture({ name: 'Test Method #2', technicalName: 'shipping_test_2', position: 3, salesChannels});
                    })
                    .then(() => {
                        return cy.createShippingFixture({ name: 'Test Method #3', technicalName: 'shipping_test_3', position: -2, salesChannels});
                    })
                    .then(() => {
                        return cy.createShippingFixture({ name: 'Test Method #4', technicalName: 'shipping_test_4', position: 4, salesChannels});
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
                        cy.get(page.elements.offCanvasCart).should('be.visible');
                        cy.get('.line-item-label').contains(product.name);

                        // Go to cart
                        cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

                        // check for correct collapsed state at page initialization
                        cy.get(`${page.elements.shippingMethodsContainer}`)
                            .should('be.visible')
                            .children('.shipping-method')
                            .should('have.length', 5);

                        cy.get(`${page.elements.shippingMethodsCollapseContainer}`).should('exist');
                        cy.get(`${page.elements.shippingMethodsCollapseContainer} > .shipping-method`).should('not.be.visible');
                        cy.get(`${page.elements.shippingMethodsCollapseTrigger}`)
                            .should('be.visible')
                            .should('contain', 'Show more');

                        // click collapse trigger to show other payment methods
                        cy.get(`${page.elements.shippingMethodsCollapseTrigger}`).click();
                        cy.get(`${page.elements.shippingMethodsCollapseContainer} > .shipping-method`).should('be.visible');
                        cy.get(`${page.elements.shippingMethodsCollapseTrigger}`).should('contain', 'Show less');

                        // click it again to collapse methods again
                        cy.get(`${page.elements.shippingMethodsCollapseTrigger}`).click();
                        cy.get(`${page.elements.shippingMethodsCollapseContainer}`).should('exist'); // wait for collapse to finish transition
                        cy.get(`${page.elements.shippingMethodsCollapseContainer} > .shipping-method`).should('not.be.visible');
                        cy.get(`${page.elements.shippingMethodsCollapseTrigger}`).should('contain', 'Show more');

                        const defaultShippingMethodName = initialShippingMethods[defaultShippingMethodId];
                        const remainingShippingMethod = defaultShippingMethodName === SHIPPING_METHOD_STANDARD
                            ? SHIPPING_METHOD_EXPRESS : SHIPPING_METHOD_STANDARD;

                        const expectedOrder = {
                            0: defaultShippingMethodName,   // position:  1
                            1: 'Test Method #3',            // position: -2
                            2: 'Test Method #1',            // position: -1
                            3: remainingShippingMethod,     // position:  1
                            4: 'Test Method #2',            // position:  3
                            5: 'Test Method #4',            // position:  4
                        };

                        cy.get(`${page.elements.shippingMethodsContainer} .shipping-method-description`)
                            .each(($div, index) => {
                                expect($div.text().trim()).to.equal(expectedOrder[index]);
                            });
                    });
            });
        });


    });
});
