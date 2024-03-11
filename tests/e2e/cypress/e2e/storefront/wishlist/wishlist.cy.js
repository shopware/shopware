import products from '../../../fixtures/listing-pagination-products.json';

const product = {
    "id": "6dfd9dc216ab4ac99598b837ac600368",
    "name": "Test product 1",
    "stock": 1,
    "productNumber": "RS-1",
    "descriptionLong": "Product description",
    "price": [
        {
            "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
            "net": 8.40,
            "linked": false,
            "gross": 10,
        },
    ],
    "url": "/product-name.html",
    "manufacturer": {
        "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
        "name": "Test variant manufacturer",
    },
};

/**
 * @package checkout
 */
describe('Wishlist: for wishlist', () => {
    beforeEach(() => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.cart.wishlistEnabled': true,
                        'core.listing.productsPerPage': 4,
                    },
                },
            };

            return cy.request(requestConfig);
        });

        return cy.createCustomerFixtureStorefront().then(() => {
            return cy.createProductFixture(product).then(() => {
                cy.setCookie('wishlist-enabled', '1');
            });
        });
    });

    it('@wishlist: Wishlist state is set correctly', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.window().then((win) => {
            cy.expect(win.salesChannelId).to.not.empty;
            cy.expect(win.customerLoggedInState).to.equal(0);
            cy.expect(win.wishlistEnabled).to.equal(1);

            cy.visit('/account/login');

            // Login
            cy.get('.login-card').should('be.visible');
            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get('.login-submit [type="submit"]').click();

            cy.window().then((win) => {
                cy.expect(win.customerLoggedInState).to.equal(1);
            });
        });
    });

    it('@wishlist: Heart icon badge display on header', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.window().then(() => {
            cy.get('.header-actions-btn .header-wishlist-icon .icon-heart svg').should('be.visible');
        });
    });

    it('@wishlist: Heart icon badge display on product box in product listing', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.get('.product-box .product-wishlist-action-circle').first().should('be.visible');
        cy.get('.product-box .product-wishlist-action-circle').first().should('have.class', 'product-wishlist-not-added');
        cy.get('.product-box .product-wishlist-action-circle').first().get('.icon-wishlist-not-added').should('be.visible');
        cy.get('.product-box .product-wishlist-action-circle').first().should('not.have.class', 'product-wishlist-added');

        cy.get('.product-box .product-wishlist-action-circle').first().click();

        cy.get('.product-box .product-wishlist-action-circle').first().should('have.class', 'product-wishlist-added');
        cy.get('.product-box .product-wishlist-action-circle').first().get('.icon-wishlist-added').first().should('be.visible');
        cy.get('.product-box .product-wishlist-action-circle').first().should('not.have.class', 'product-wishlist-not-added');
    });

    it('@wishlist: Heart icon badge display in product detail', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.window().then(() => {
            cy.get('.product-image-wrapper').click();

            cy.get('.product-wishlist-action').first().should('be.visible');

            cy.get('.product-wishlist-action.product-wishlist-not-added').first().should('be.visible');
            cy.get('.product-wishlist-action').first().should('not.have.class', 'product-wishlist-added');
            cy.get('.product-wishlist-btn-content').first().contains('Add to wishlist');

            cy.get('.product-wishlist-action').first().click();

            cy.get('.product-wishlist-action.product-wishlist-added').first().should('be.visible');
            cy.get('.product-wishlist-action').first().should('not.have.class', 'product-wishlist-not-added');
            cy.get('.product-wishlist-btn-content').first().contains('Remove from wishlist');
        });
    });

    it('@wishlist: Heart icon badge display the counter', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.window().then(() => {
            cy.get('#wishlist-basket').should('not.be.visible');
            cy.get('.product-box .product-wishlist-action-circle').first().click();

            cy.get('#wishlist-basket').should('be.visible');
            cy.get('#wishlist-basket').contains('1');

            cy.get('.product-box .product-wishlist-action-circle').first().click();
            cy.get('#wishlist-basket').should('not.be.visible');
        });
    });

    it('@wishlist: Click add to wishlist icon redirect to login page if cookie is not accepted', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.window().then(() => {
            cy.clearCookie('wishlist-enabled');

            cy.get('#wishlist-basket').should('not.be.visible');
            cy.get('.product-box .product-wishlist-action-circle').first().click();

            cy.get('.login-card').should('be.visible');
            cy.url().should('include', '/account/login?redirectTo=frontend.wishlist.add.after.login');

            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get('.login-submit [type="submit"]').click();

            cy.get('.flashbags').should('be.visible');
            cy.get('.flashbags .alert-success').should('be.visible');

            cy.get('.alert-content').contains('You have successfully added the product to your wishlist.');
        });
    });

    it('@wishlist: Order in which the products are displayed is based on the time they were added to the wishlist', { tags: ['pa-checkout'] }, () => {
        cy.createProductFixture({
            "id": "6dfd9dc216ab4ac99598b837ac600369",
            "name": "Test product 2",
            "stock": 1,
            "productNumber": "RS-2",
            "descriptionLong": "Product description",
            "price": [
                {
                    "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                    "net": 8.40,
                    "linked": false,
                    "gross": 10,
                },
            ],
            "url": "/product-name.html",
            "manufacturer": {
                "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                "name": "Test variant manufacturer",
            },
        });

        cy.visit('/');

        cy.intercept({
            method: 'POST',
            url: '/wishlist/guest-pagelet',
        }).as('guestPagelet');

        cy.get(`.product-wishlist-${product.id}`).first().should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('have.class', 'product-wishlist-not-added');

        cy.get(`.product-wishlist-${product.id}`).first().click();

        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().should('be.visible');
        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().should('have.class', 'product-wishlist-not-added');

        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().click();

        cy.visit('/wishlist');
        cy.title().should('eq', 'Your wishlist');

        cy.wait('@guestPagelet')
            .its('response.statusCode').should('equal', 200);

        cy.get('.cms-listing-col').eq(0).contains('Test product 2');
        cy.get('.cms-listing-col').eq(1).contains(product.name);
    });

    it('@wishlist: Heart icon badge display on product box in product listing pagination', { tags: ['pa-checkout'] }, () => {
        Array.from(products).forEach(product => cy.createProductFixture(product));

        cy.visit('/');

        Array.from(products).slice(0, 4).forEach(item => {
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('have.class', 'product-wishlist-not-added');
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('not.have.class', 'product-wishlist-added');
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().click();

            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('have.class', 'product-wishlist-added');
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('not.have.class', 'product-wishlist-not-added');
        });

        cy.get('#wishlist-basket').contains('4');

        cy.get('.pagination-nav .page-next').eq(0).click();

        Array.from(products).slice(4, 8).forEach(item => {
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('have.class', 'product-wishlist-not-added');
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('not.have.class', 'product-wishlist-added');

            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().click();

            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('have.class', 'product-wishlist-added');
            cy.get(`.product-wishlist-${item.id}`, {timeout: 10000}).first().should('not.have.class', 'product-wishlist-not-added');
        });

        cy.get('#wishlist-basket').contains('8');
    });
});
