const orderCount = 11;

describe('Account: Paginated orders', () => {
    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createCustomerFixtureStorefront()
        }).then(() => {
            return cy.searchViaAdminApi({
                endpoint: 'product',
                data: {
                    field: 'name',
                    value: 'Product name'
                }
            });
        }).then((result) => {
            for (let i = 1; i <= orderCount; i++) {
                cy.createOrder(result.id, {
                    username: 'test@example.com',
                    password: 'shopware'
                });
            }
        })
    });

    it('@customer: orders pagination', () => {
        cy.server();
        cy.route({
            url: '/account/order',
            method: 'post'
        }).as('loadNextPage');

        // Login
        cy.visit('/account/order');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Orders pagination is visible
        cy.get('.account-orders-pagination').should('be.visible');

        // Orders are on first page
        cy.get('.pagination-nav .page-item.page-first').should('have.class', 'disabled');

        // Orders amount to 10 on first page
        cy.get('.order-wrapper').should('have.length', 10);

        // Navigate to next page
        cy.get('.pagination-nav .page-next').eq(0).click();
        cy.wait('@loadNextPage').should('have.property', 'status', 200);

        // Orders amount to 1 on second page
        cy.get('.order-wrapper').should('have.length', 1);

        // Orders are on last page
        cy.get('.pagination-nav .page-item.page-last').should('have.class', 'disabled');
    });
});
