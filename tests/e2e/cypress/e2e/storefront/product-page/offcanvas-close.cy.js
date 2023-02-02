import CheckoutPageObject from '../../../support/pages/checkout.page-object';

let product = {};

describe('Test if the offcanvas menus could be closed with the browser back button', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            cy.visit('/');
        });
    });

    it('Should close offcanvas on browser back', { tags: ['pa-checkout'] }, () => {
        cy.featureIsActive('v6.5.0.0').then((isActive) => {
            const offCanvasShow = isActive ?  '.offcanvas.show': '.offcanvas.is-open'

            // set to mobile viewport
            cy.viewport(360, 640);

            // go to product
            cy.get('.search-toggle-btn').click();
            cy.get('.header-search-input').should('be.visible');
            cy.get('.header-search-input').type(product.name);
            cy.get('.search-suggest-product-name').contains(product.name);
            cy.get('.search-suggest-product-price').contains(product.price[0].gross);
            cy.get('.search-suggest-product-name').click();
            cy.get('.product-detail-name').contains(product.name);

            // open offcanvas (product description)
            if (isActive) {
                cy.get('.description-tab').click();
            } else {
                cy.get('#description-tab').click();
            }

            cy.get(offCanvasShow).should('be.visible');
            cy.get('.offcanvas .product-detail-description-title').contains(product.name);

            // close offcanvas with browser back
            cy.go('back');
            cy.get('.offcanvas').should('not.exist');

            // ensure normal closing via click still works
            cy.get('.header-cart').click();
            cy.get('.offcanvas').should('be.visible');
            cy.get('.offcanvas .offcanvas-cart-header').contains('Shopping cart');
            cy.get('.offcanvas .offcanvas-close').click();
            cy.get('.offcanvas').should('not.exist');

            // ensure normal closing via click still works
            cy.get('.header-cart').click();
            cy.get(offCanvasShow).should('be.visible');
            cy.get('.offcanvas .offcanvas-cart-header').contains('Shopping cart');
            cy.get(`${offCanvasShow} .offcanvas-close`).click();
            cy.get('.offcanvas').should('not.exist');

            // ensure, it is still the product detail page
            cy.get('.product-detail-name').contains(product.name);
        })
    });

    it('Should close offcanvas on clicking on backdrop', { tags: ['pa-checkout'] }, () => {
        cy.featureIsActive('v6.5.0.0').then((isActive) => {
            const page = new CheckoutPageObject();

            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = isActive ? '.line-item' : '.cart-item';

            // add product to cart
            cy.get('.header-search-input')
                .should('be.visible')
                .type(product.name);
            cy.contains('.search-suggest-product-name', product.name).click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get(`${lineItemSelector}-label`).contains(product.name);

            // close offcanvas with backdrop click
            /** @deprecated tag:v6.5.0 - Use `modal-backdrop` instead */
            const backdropSelector =  isActive ? '.offcanvas-backdrop' : '.modal-backdrop';

            cy.get(backdropSelector).click();
            cy.get('.offcanvas').should('not.exist');
            cy.get(backdropSelector).should('not.exist');

            // ensure, it is still the product detail page
            cy.get('.product-detail-name').contains(product.name);

            // ensure normal closing via click still works
            cy.get('.header-cart').click();
            cy.get('.offcanvas').should('be.visible');
            cy.get('.offcanvas .offcanvas-cart-header').contains('Shopping cart');
            cy.get('.offcanvas .offcanvas-close').click();
            cy.get('.offcanvas').should('not.exist');

            // ensure, it is still the product detail page
            cy.get('.product-detail-name').contains(product.name);
        });
    });
});
