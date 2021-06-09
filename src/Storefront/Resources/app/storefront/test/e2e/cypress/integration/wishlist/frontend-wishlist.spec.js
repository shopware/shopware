import AccountPageObject from "../../support/pages/account.page-object";

const page = new AccountPageObject();

const customer = {
    firstName: 'John',
    lastName: 'Doe',
    email: "tester@example.com",
    password: "shopware"
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
            "gross": 10
        }
    ],
    "url": "/product-name.html",
    "manufacturer": {
        "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
        "name": "Test variant manufacturer"
    },
};

describe('Wishlist: for wishlist page', () => {
    beforeEach(() => {
        cy.setCookie('wishlist-enabled', '1');

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

        return cy.createCustomerFixtureStorefront(customer).then(() => {
            return cy.createProductFixture(product).then(response => {
                return cy.setProductWishlist({
                    productId: response.id,
                    customer: {
                        username: customer.email,
                        password: customer.password
                    }
                }).then(() => {
                    cy.visit('/wishlist');
                });
            })
        })
    });

    it('@wishlist does some simple testing of the wishlist', () => {
        cy.visit('/account/login');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.visit('/wishlist');
        cy.get('.cms-listing-row .cms-listing-col').contains(product.name);
        cy.get(`.cms-listing-row .cms-listing-col`).contains(product.manufacturer.name);
    });

    it('@wishlist does load wishlist page on guest state', () => {
        cy.server();
        cy.route({
            url: '/wishlist/guest-pagelet',
            method: 'post'
        }).as('guestPagelet');

        cy.visit('/');

        cy.window().then((win) => {
            win.localStorage.setItem('wishlist-' + win.salesChannelId, JSON.stringify({[product.id]: "20201220"}));
            cy.visit('/wishlist');

            cy.title().should('eq', 'Your wishlist');

            cy.wait('@guestPagelet').then(xhr => {
                expect(xhr).to.have.property('status', 200);
            });

            cy.get('.cms-listing-row .cms-listing-col').contains(product.name);
            cy.get(`.cms-listing-row .cms-listing-col`).contains(product.manufacturer.name);
            cy.get('.product-wishlist-form [type="submit"]').click();

            cy.wait('@guestPagelet').then(xhr => {
                expect(xhr).to.have.property('status', 200);
                expect(win.localStorage.getItem('wishlist-' + win.salesChannelId)).to.equal(null)
            });

            cy.get('.cms-listing-row').find('h1').contains('Your wishlist is empty')
            cy.get('.cms-listing-row').find('p').contains('Keep an eye on products you like by adding them to your wishlist.');
        });
    });

    it('@wishlist add to cart button work on guest page', () => {
        cy.server();
        cy.route({
            url: '/wishlist/guest-pagelet',
            method: 'post'
        }).as('guestPagelet');

        cy.route({
            url: '/checkout/line-item/add',
            method: 'post'
        }).as('add-to-cart');

        cy.route({
            url: '/widgets/checkout/info',
            method: 'get'
        }).as('offcanvas');

        cy.visit('/');

        cy.window().then(win => {
            win.localStorage.setItem('wishlist-' + win.salesChannelId, JSON.stringify({[product.id]: "20201220"}));

            cy.visit('/wishlist');

            cy.title().should('eq', 'Your wishlist')

            cy.wait('@guestPagelet').then(xhr => {
                expect(xhr).to.have.property('status', 200);
                cy.get('.cms-listing-row .cms-listing-col').contains(product.name);
                cy.get(`.cms-listing-row .cms-listing-col`).contains(product.manufacturer.name);
                cy.get('.cms-listing-row .cms-listing-col .product-action .btn-buy').should('exist');
                cy.get('.cms-listing-row .cms-listing-col .product-action .btn-buy').click();

                cy.wait('@add-to-cart').then(xhr => {
                    expect(xhr).to.have.property('status', 200);
                });

                cy.wait('@offcanvas').then(xhr => {
                    expect(xhr).to.have.property('status', 200);
                    cy.get('.offcanvas.is-open.cart-offcanvas').should('exist');
                    cy.get('.offcanvas.is-open.cart-offcanvas').find('.cart-item-label').contains(product.name);

                    // Wishlist product should still exist
                    cy.get('.cms-listing-row .cms-listing-col').contains(product.name);
                    cy.get(`.cms-listing-row .cms-listing-col`).contains(product.manufacturer.name);
                });
            });
        });
    });

    it('@wishlist remove product of wishlist', () => {
        cy.visit('/account/login');
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.visit('/wishlist');
        cy.get('.cms-listing-row .cms-listing-col').contains(product.name);

        cy.get('.product-wishlist-form [type="submit"]').click();

        cy.get('.alert-success').contains('You have successfully removed the product from your wishlist.');
    })
});
