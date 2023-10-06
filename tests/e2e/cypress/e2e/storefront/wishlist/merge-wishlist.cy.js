const customer = {
    firstName: 'Y',
    lastName: 'Tran',
    email: "y.tran@shopware.com",
    password: "shopware",
};

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
                        'core.cart.wishlistEnabled': true, // enable wishlist
                    },
                },
            };

            return cy.request(requestConfig);
        });

        return cy.createCustomerFixtureStorefront(customer).then(() => {
            return cy.createProductFixture(product).then(() => {
                cy.setCookie('wishlist-enabled', '1');
            });
        });
    });

    it('@wishlist: Wishlist can be merge from anonymous user to registered users', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.intercept({
            method: 'POST',
            url: '/wishlist/merge',
        }).as('wishlistMerge');

        cy.get(`.product-wishlist-${product.id}`).first().should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('have.class', 'product-wishlist-not-added');
        cy.get(`.product-wishlist-${product.id}`).first().get('.icon-wishlist-not-added').should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('not.have.class', 'product-wishlist-added');

        cy.get(`.product-wishlist-${product.id}`).first().click();

        cy.window().then((win) => {
            cy.expect(win.wishlistEnabled).to.equal(1);
        });

        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.wait('@wishlistMerge').then(() => {
            cy.get('#wishlist-basket').contains('1');
            cy.get('.flashbags .alert .alert-content-container .alert-content').contains('Your wishlist might contain products that have been added and saved during a previous visit.');
        });

        cy.visit('/');

        cy.get(`.product-wishlist-${product.id}`).first().should('have.class', 'product-wishlist-added');
        cy.get(`.product-wishlist-${product.id}`).first().should('not.have.class', 'product-wishlist-not-added');
        cy.get(`.product-wishlist-${product.id}`).first().get('.icon-wishlist-added').should('be.visible');
    });

    it('@wishlist: Wishlist can be merge from anonymous user to registered users with same product', { tags: ['pa-checkout'] }, () => {
        cy.visit('/');

        cy.intercept({
            method: 'POST',
            url: '/wishlist/add/**',
        }).as('wishlistAdd');

        cy.intercept({
            method: 'POST',
            url: '/wishlist/merge',
        }).as('wishlistMerge');

        cy.visit('/account/login');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.visit('/');

        cy.get(`.product-wishlist-${product.id}`).first().should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('have.class', 'product-wishlist-not-added');
        cy.get(`.product-wishlist-${product.id}`).first().get('.icon-wishlist-not-added').should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('not.have.class', 'product-wishlist-added');

        cy.get(`.product-wishlist-${product.id}`).first().click();

        cy.wait('@wishlistAdd').then( () => {
            cy.get('#wishlist-basket').contains('1');

            cy.visit('/account/logout');
            cy.visit('/');
            cy.reload(true);
        });

        cy.get(`.product-wishlist-${product.id}`).first().click();

        // Login
        cy.visit('/account/login');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.wait('@wishlistMerge').then(() => {
            cy.get('.flashbags .alert .alert-content-container .alert-content').contains('Your wishlist might contain products that have been added and saved during a previous visit.');
            cy.get('#wishlist-basket').contains('1');
        });
    });

    it('@wishlist: Wishlist can be merge from anonymous user to registered users with different product', { tags: ['pa-checkout'] }, () => {
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
            url: '/wishlist/add/**',
        }).as('wishlistAdd');
        cy.intercept({
            method: 'POST',
            url: '/wishlist/merge',
        }).as('wishlistMerge');

        cy.visit('/account/login');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.visit('/');

        cy.get(`.product-wishlist-${product.id}`).first().should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('have.class', 'product-wishlist-not-added');

        cy.get(`.product-wishlist-${product.id}`).first().click();

        cy.wait('@wishlistAdd').then( () => {
            cy.get('#wishlist-basket').contains('1');

            cy.visit('/account/logout');
            cy.visit('/');
        });

        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().should('be.visible');
        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().should('have.class', 'product-wishlist-not-added');

        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().click();

        // Login
        cy.visit('/account/login');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.wait('@wishlistMerge').then( () => {
            cy.get('.flashbags .alert .alert-content-container .alert-content').contains('Your wishlist might contain products that have been added and saved during a previous visit.');
            cy.get('#wishlist-basket').contains('2');
        });
    });

    it('@wishlist: The order in which the products are displayed is based on the time they were added to the wishlist', { tags: ['pa-checkout'] }, () => {
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
            url: '/wishlist/add/**',
        }).as('wishlistAdd');

        cy.intercept({
            method: 'POST',
            url: '/wishlist/merge',
        }).as('wishlistMerge');

        cy.visit('/account/login');

        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.visit('/');

        // add to wishlist with registered users
        cy.get(`.product-wishlist-${product.id}`).first().should('be.visible');
        cy.get(`.product-wishlist-${product.id}`).first().should('have.class', 'product-wishlist-not-added');

        cy.get(`.product-wishlist-${product.id}`).first().click();

        cy.wait('@wishlistAdd').then(() => {
            cy.get('#wishlist-basket').contains('1');

            cy.visit('/account/logout');
            cy.visit('/');
        });

        // add to wishlist with anonymous user
        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().should('be.visible');
        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().should('have.class', 'product-wishlist-not-added');

        cy.get(`.product-wishlist-6dfd9dc216ab4ac99598b837ac600369`).first().click();

        cy.get('#wishlist-basket').contains('1');

        cy.visit('/account/login');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get('.login-submit [type="submit"]').click();

        cy.visit('/wishlist');

        cy.wait('@wishlistMerge');
        cy.get('#wishlist-basket').contains('2');

        cy.visit('/wishlist');
        cy.get('.cms-listing-col').contains('Test product 2');
        cy.get('.cms-listing-col').contains('Test product 1');
    });
});
