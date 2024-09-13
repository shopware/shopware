import CheckoutPageObject from '../../../support/pages/checkout.page-object';

let digitalProduct = {};
let physicalProduct = {};

describe('Test checkout with downloadable products', () => {
    beforeEach(() => {
        cy.createProductFixture({
            name: 'Digital product',
            productNumber: 'RS-11111',
            maxPurchase: 1,
            downloads: [
                {
                    media: {
                        url: 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==',
                        private: true,
                    },
                },
            ],
        })
            .then((result) => {
                digitalProduct = result;
                return cy.createProductFixture({
                    name: 'Physical product',
                    productNumber: 'RS-22222',
                });
            })
            .then((result) => {
                physicalProduct = result;
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

    it('@base @checkout: should checkout with downloadable products only', { tags: ['pa-checkout'] }, () => {
        cy.window().then(() => {
            const page = new CheckoutPageObject();

            // add product to cart
            cy.get('.header-search-input')
                .should('be.visible')
                .type(digitalProduct.name);
            cy.contains('.search-suggest-product-name', digitalProduct.name).click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get(`${page.elements.lineItem}-label`).contains(digitalProduct.name);
            cy.get('.offcanvas-shipping-info').should('not.exist');

            // Go to cart
            cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

            cy.get(`${page.elements.paymentFormConfirm}`).should('be.visible');
            cy.get(`${page.elements.shippingFormConfirm}`).should('not.exist');

            cy.get('.checkout-confirm-tos-label').scrollIntoView();
            cy.get('.checkout-confirm-tos-label').click(1, 1);
            cy.get('.checkout-confirm-revocation-label').scrollIntoView();
            cy.get('.checkout-confirm-revocation-label').click(1, 1);

            cy.get('#confirmFormSubmit').scrollIntoView();
            cy.get('#confirmFormSubmit').click();

            // Finish page
            cy.get('.finish-address-billing').should('be.visible');
            cy.get('.finish-address-shipping').should('not.exist');
        });
    });

    it('@base @checkout: should checkout with downloadable and physical mixed', {tags: ['pa-checkout']}, () => {
        cy.window().then(() => {
            const page = new CheckoutPageObject();

            // add digital product to cart
            cy.get('.header-search-input')
                .should('be.visible')
                .type(digitalProduct.name);
            cy.contains('.search-suggest-product-name', digitalProduct.name).click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get(`${page.elements.offCanvasCart} .offcanvas-close`).click();
            cy.get(page.elements.offCanvasCart).should('not.exist');

            // add physical product to cart
            cy.get('.header-search-input')
                .should('be.visible')
                .type(physicalProduct.name, { force: true });
            cy.contains('.search-suggest-product-name', physicalProduct.name).click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get(`${page.elements.lineItem}-label`).contains(digitalProduct.name);
            cy.get(`${page.elements.lineItem}-label`).contains(physicalProduct.name);
            cy.get('.offcanvas-shipping-info').should('be.visible');

            // Go to cart
            cy.get('.offcanvas-cart-actions [href="/checkout/confirm"]').click();

            cy.get(`${page.elements.paymentFormConfirm}`).should('be.visible');
            cy.get(`${page.elements.shippingFormConfirm}`).should('be.visible');

            cy.get('.checkout-confirm-tos-label').scrollIntoView();
            cy.get('.checkout-confirm-tos-label').click(1, 1);
            cy.get('.checkout-confirm-revocation-label').scrollIntoView();
            cy.get('.checkout-confirm-revocation-label').click(1, 1);

            cy.get('#confirmFormSubmit').scrollIntoView();
            cy.get('#confirmFormSubmit').click();

            // Finish page
            cy.get('.finish-address-billing').should('be.visible');
            cy.get('.finish-address-shipping').should('be.visible');
        });
    });
});
