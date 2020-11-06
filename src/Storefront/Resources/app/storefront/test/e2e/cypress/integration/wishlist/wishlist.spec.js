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

    it('@wishlist: Wishlist state is set correctly', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

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
        })
    });

    it('@wishlist: Heart icon badge display on header', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            cy.get('.header-actions-btn .header-wishlist-icon .icon-heart svg').should('be.visible');
        })
    });

    it('@wishlist: Heart icon badge display on product box in product listing', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            let heartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

            heartIcon.first().should('be.visible');
            heartIcon.first().should('have.class', 'product-wishlist-not-added');
            heartIcon.get('.icon-wishlist-not-added').should('be.visible');
            heartIcon.should('not.have.class', 'product-wishlist-added');

            heartIcon.click();

            heartIcon = cy.get('.product-box .product-wishlist-action-circle').first();

            heartIcon.should('have.class', 'product-wishlist-added');
            heartIcon.get('.icon-wishlist-added').first().should('be.visible');
            heartIcon.should('not.have.class', 'product-wishlist-not-added');
        })
    });

    it('@wishlist: Heart icon badge display in produt detail', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            cy.get('.product-image-wrapper').click();

            cy.get('.product-wishlist-action').first().should('be.visible');

            cy.get('.product-wishlist-btn-content.text-wishlist-not-added').first().should('be.visible');
            cy.get('.product-wishlist-btn-content.text-wishlist-added').first().should('not.be.visible');
            cy.get('.product-wishlist-btn-content.text-wishlist-not-added').first().contains('Add to wish list');

            cy.get('.product-wishlist-action').first().click();

            cy.get('.product-wishlist-btn-content.text-wishlist-added').first().should('be.visible');
            cy.get('.product-wishlist-btn-content.text-wishlist-not-added').first().should('not.be.visible');
            cy.get('.product-wishlist-btn-content.text-wishlist-added').first().contains('Added to wish list');
        })
    });

    it('@wishlist: Heart icon badge display the counter', () => {
        cy.visit('/');

        cy.window().then((win) => {
            if (!win.Feature.isActive('FEATURE_NEXT_10549')) {
                return;
            }

            cy.get('#wishlist-basket').should('not.be.visible');
            cy.get('.product-box .product-wishlist-action-circle').first().click();

            cy.get('#wishlist-basket').should('be.visible');
            cy.get('#wishlist-basket').contains('1');

            cy.get('.product-box .product-wishlist-action-circle').first().click();
            cy.get('#wishlist-basket').should('not.be.visible');
        })
    });
});
