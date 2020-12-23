let product = {};

describe('Wishlist: for wishlist', () => {
    beforeEach(() => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.cart.wishlistEnabled': true // enable wishlist
                    }
                }
            };

            return cy.request(requestConfig);
        });
        cy.setCookie('wishlist-enabled', '1');

        return cy.createCustomerFixtureStorefront().then(() => {
            return cy.createProductFixture().then(() => {
                return cy.createDefaultFixture('category')
            }).then(() => {
                return cy.fixture('product');
            }).then((res) => {
                product = res;
            })
        })
    });
    // @TODO: Fix failing request with NEXT-12846
    it.skip('@wishlist: Wishlist can be merge from anonymous user to registered users', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            cy.server();
            cy.route({
                url: '/wishlist/merge',
                method: 'post'
            }).as('wishlistMerge');

            let heartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

            heartIcon.first().should('be.visible');
            heartIcon.first().should('have.class', 'product-wishlist-not-added');
            heartIcon.get('.icon-wishlist-not-added').should('be.visible');
            heartIcon.should('not.have.class', 'product-wishlist-added');

            heartIcon.click();
            cy.window().then((win) => {
                cy.expect(win.customerLoggedInState).to.equal(0);
                cy.expect(win.wishlistEnabled).to.equal(1);
            });

            cy.visit('/account/login');

            // Login
            cy.get('.login-card').should('be.visible');
            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get('.login-submit [type="submit"]').click();

            cy.window().then((win) => {
                cy.expect(win.customerLoggedInState).to.equal(1);
            });

            cy.wait('@wishlistMerge').then(() => {
                cy.get('#wishlist-basket').contains('1');
                cy.get('.flashbags .alert .alert-content-container .alert-content').contains('Your wishlist might contain products that have been added and saved during a previous visit.');
            });

            cy.visit('/');

            heartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

            heartIcon.should('have.class', 'product-wishlist-added');
            heartIcon.get('.icon-wishlist-added').first().should('be.visible');
            heartIcon.should('not.have.class', 'product-wishlist-not-added');
        });
    });

    // @TODO: Fix failing request with NEXT-12846
    it.skip('@wishlist: Wishlist can be merge from anonymous user to registered users with same product', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            cy.server();
            cy.route({
                url: '/wishlist/add/**',
                method: 'post'
            }).as('wishlistAdd');

            cy.route({
                url: '/wishlist/merge',
                method: 'post'
            }).as('wishlistMerge');

            cy.visit('/account/login');

            // Login
            cy.get('.login-card').should('be.visible');
            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get('.login-submit [type="submit"]').click();

            cy.visit('/');

            let heartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

            heartIcon.first().should('be.visible');
            heartIcon.first().should('have.class', 'product-wishlist-not-added');
            heartIcon.get('.icon-wishlist-not-added').should('be.visible');
            heartIcon.should('not.have.class', 'product-wishlist-added');

            heartIcon.first().click({force: true});
            heartIcon.first().click(1, 1);

            cy.wait('@wishlistAdd').then( () => {
                cy.get('#wishlist-basket').contains('1');

                cy.visit('/account/logout');
                cy.visit('/');
            });

            let lastHeartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

            lastHeartIcon.first().click(1, 1);

            // Login
            cy.visit('/wishlist');
            cy.get('.login-card').should('be.visible');
            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get('.login-submit [type="submit"]').click();

            cy.wait('@wishlistMerge').then(() => {
                cy.get('.flashbags .alert .alert-content-container .alert-content').contains('Your wishlist might contain products that have been added and saved during a previous visit.');
                cy.reload(true);
            });
            cy.get('#wishlist-basket').contains('1');
        });
    });

    // @TODO: Fix failing request with NEXT-12846
    it.skip('@wishlist: Wishlist can be merge from anonymous user to registered users with different product', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            cy.server();
            cy.route({
                url: '/wishlist/add/**',
                method: 'post'
            }).as('wishlistAdd');

            cy.route({
                url: '/wishlist/merge',
                method: 'post'
            }).as('wishlistMerge');

            cy.visit('/account/login');

            cy.window().then((win) => {
                cy.createProductFixture(
                    {
                        "id": "6dfd9dc216ab4ac99598b837ac600368",
                        "name": "Product name 2",
                        "stock": 1,
                        "productNumber": "RS-1",
                        "descriptionLong": "Product description",
                        "price": [
                            {
                                "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                                "net": 8.40,
                                "linked": false,
                                "gross": 10
                            }
                        ],
                        "url": "/product-name.html"
                    });

                // Login
                cy.get('.login-card').should('be.visible');
                cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
                cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                cy.get('.login-submit [type="submit"]').click();

                cy.visit('/');

                let heartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

                heartIcon.first().should('be.visible');
                heartIcon.first().should('have.class', 'product-wishlist-not-added');
                heartIcon.get('.icon-wishlist-not-added').should('be.visible');
                heartIcon.should('not.have.class', 'product-wishlist-added');

                heartIcon.first().click({force: true});
                heartIcon.first().click(1, 1);

                cy.wait('@wishlistAdd').then( () => {
                    cy.get('#wishlist-basket').contains('1');

                    cy.visit('/account/logout');
                    cy.visit('/');
                });

                let lastHeartIcon = cy.get('.product-box .product-wishlist-action-circle').last();

                lastHeartIcon.first().click(1, 1);

                // Login
                cy.visit('/wishlist');
                cy.get('.login-card').should('be.visible');
                cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
                cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                cy.get('.login-submit [type="submit"]').click();

                cy.wait('@wishlistMerge').then( () => {
                    cy.get('.flashbags .alert .alert-content-container .alert-content').contains('Your wishlist might contain products that have been added and saved during a previous visit.');
                    cy.reload(true);
                });
                cy.get('#wishlist-basket').contains('2');
            })
        });
    });
});
