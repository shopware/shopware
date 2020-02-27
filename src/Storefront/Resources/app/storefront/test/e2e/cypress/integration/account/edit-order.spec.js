describe('Account: Edit order', () => {
    beforeEach(() => {
        cy.createProductFixture();
        cy.createCustomerFixtureStorefront();
        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Create an order -> should be a fixture
        cy.get('.home-link').click();
        cy.get('.btn-buy').click();
        cy.get('.offcanvas-cart-actions .btn.btn-block.btn-primary').click();
        cy.get('.custom-control.custom-checkbox').click();
        cy.get('#confirmFormSubmit').click();
        cy.visit('/account/order');
    });

    it('@package @customer: reorder order', () => {
        // Order detail is expandable
        cy.get('.order-table:nth-of-type(1) .order-hide-btn').click();
        cy.wait(400);
        cy.get('.order-table:nth-of-type(1) .order-hide-btn').click();

        // Reorder order
        cy.get('.order-table-header-context-menu').click();
        cy.get('.order-table-header-context-menu-content-form button').click();
        cy.get('.btn.btn-block.btn-primary').click();
        cy.get('.custom-control.custom-checkbox').click();
        cy.get('#confirmFormSubmit').click();
        cy.visit('/account/order');
    });

    it('@package @customer: cancel order', () => {
        // cancel order
        cy.get('.order-table-header-context-menu').click();
        cy.get('.dropdown-menu > [type="button"]').click();
        cy.get('form > .btn').click();
        cy.get('.order-table-header-order-status').contains('Cancelled');
    });

    it('@package @customer: change payment', () => {
        // change payment
        cy.get('.order-table-header-context-menu').click();
        cy.get('a.order-table-header-context-menu-content-link').click();
        cy.get('.card-body > [data-toggle="modal"]').click();
        cy.get('label[for~="paymentMethod2"]').click();
        cy.get('#confirmPaymentForm > .btn-primary').click();
        cy.get('.custom-checkbox label').click(1, 1);
        cy.get('#confirmOrderForm > .btn').scrollIntoView();
        cy.get('#confirmOrderForm > .btn').click();
        cy.get('.finish-order-details .checkout-card .card-body p:first').contains('Paid in advance');
    });
});
