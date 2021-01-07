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
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/${Cypress.env('apiVersion')}/_action/system-config/batch`,
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

    it.skip('@wishlist does some simple testing of the wishlist', () => {
        // todo handle when @shopware-ag/e2e-testsuite-platform support call `/store-api/v*/*`

        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.cms-listing-row .cms-listing-col').contains(product.name);
        cy.get(`.cms-listing-row .cms-listing-col`).contains(product.manufacturer.name);
    });

    it.skip('@wishlist does load wishlist page on guest state', () => {
        cy.server();
        cy.route({
            url: '/wishlist/guest-pagelet',
            method: 'post'
        }).as('guestPagelet');

        cy.visit('/wishlist');
        cy.title().should('eq', 'Your wishlist')

        localStorage.setItem('wishlist-products', JSON.stringify({[product.id]: "20201220"}));

        cy.wait('@guestPagelet').then(xhr => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.cms-listing-row .cms-listing-col').contains(product.name);
        cy.get(`.cms-listing-row .cms-listing-col`).contains(product.manufacturer.name);

        cy.get('.product-wishlist-form [type="submit"]').click();

        cy.wait('@guestPagelet').then(xhr => {
            expect(xhr).to.have.property('status', 200);
            expect(localStorage.getItem('wishlist-products')).to.equal(null)
        });

        cy.get('.cms-listing-row').find('h1').contains('Your wishlist is empty')
        cy.get('.cms-listing-row').find('p').contains('Keep an eye on products you like by adding them to your wishlist.')
    });

    it.skip('@wishlist remove product of wishlist', () => {
        // todo handle when @shopware-ag/e2e-testsuite-platform support call `/store-api/v*/*`
        cy.get('#loginMail').typeAndCheckStorefront(customer.email);
        cy.get('#loginPassword').typeAndCheckStorefront(customer.password);
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.cms-listing-row .cms-listing-col').contains(product.name);

        cy.get('.product-wishlist-form [type="submit"]').click();

        cy.get('.alert-success').contains('You have successfully removed the product from the wishlist.');
    })
});
