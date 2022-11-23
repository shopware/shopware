import CheckoutPageObject from '../../../support/pages/checkout.page-object';

let product = {};

const additionalData = {
    featureSet: {
        name: 'Testing feature set',
        description: 'Lorem ipsum dolor sit amet',
        features: [
            {
                type: 'referencePrice',
                id: null,
                name: null,
                position: 0
            }
        ]
    },
    unit: {
        shortCode: 'l',
        name: 'litres'
    },
    purchaseUnit: 2,
    referenceUnit: 0.33
};

/**
 * @package checkout
 */
describe('Test if essential characteristics are displayed in checkout', () => {
    beforeEach(() => {
        cy.createProductFixture(additionalData).then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            cy.visit('/');
        });
    });

    it('@checkout: Should display essential characteristics', { tags: ['pa-checkout'] }, () => {
        const page = new CheckoutPageObject();

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            // Product detail
            cy.get('.header-search-input').should('be.visible');
            cy.get('.header-search-input').type(product.name);
            cy.get('.search-suggest-product-name').contains(product.name);
            cy.get('.search-suggest-product-price').contains(product.price[0].gross);
            cy.get('.search-suggest-product-name').click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get(`${lineItemSelector}-label`).contains(product.name);

            // Go to cart
            cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();

            // Cart page
            cy.get('.cart-main-header').should('be.visible').contains('Shopping cart');

            // Essential characteristics
            cy.get(page.elements.cartItemFeatureContainer).should('be.visible');

            // We're expecting to see the reference price, as configured via the fixture
            cy.get(page.elements.cartItemFeatureContainer).should('be.visible');
            cy.get(`${page.elements.cartItemFeatureContainer}-reference-price`)
                .should('be.visible')
                .contains(`${additionalData.purchaseUnit} ${additionalData.unit.name}`)
                .contains(`${additionalData.referenceUnit} ${additionalData.unit.name}`);
        });
    });
});
