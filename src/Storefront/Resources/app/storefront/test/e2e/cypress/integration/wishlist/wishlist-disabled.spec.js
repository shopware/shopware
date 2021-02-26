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
                        'core.cart.wishlistEnabled': false // disable wishlist
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

    it('@wishlist: Wishlist state is not set', () => {
        cy.visit('/');

        cy.window().then((win) => {
            cy.expect(win.customerLoggedInState).to.equal(undefined);
            cy.expect(win.wishlistEnabled).to.equal(undefined);
        })
    });

    it('@wishlist: Heart icon badge is not display on header', () => {
        cy.visit('/');

        cy.window().then((win) => {
            cy.get('.header-actions-btn .header-wishlist-icon .icon-heart svg').should('not.be.visible');
        })
    });

    it('@wishlist: Heart icon badge not display on product box in product listing', () => {
        cy.visit('/');

        cy.window().then((win) => {
            cy.get('.product-box .product-wishlist-action-circle').should('not.be.visible');
        })
    });
});
