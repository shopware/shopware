describe('Account: Edit order', () => {
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
            return cy.createOrder(result.id, {
                username: 'test@example.com',
                password: 'shopware'
            });
        })
    });

    it('@customer: reorder order', () => {
        // Login
        cy.visit('/account/order');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Order detail is expandable
        cy.get('.order-table').should('be.visible');
        cy.get('.order-table:nth-of-type(1) .order-table-header-order-number').contains('Order number: 10000');
        cy.get('.order-table:nth-of-type(1) .order-hide-btn').click();
        cy.get('.order-detail-content').should('be.visible');

        // Re-order past order
        cy.get('.order-table-header-context-menu').click();
        cy.get('.order-table-header-context-menu-content-form button').click();
        cy.get('.btn.btn-block.btn-primary').click();
        cy.get('.custom-control.custom-checkbox input').click({force: true});
        cy.get('#confirmFormSubmit').click();

        // Verify order
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
        cy.get('.finish-ordernumber').contains('Your order number: #10001');
    });

    it('@base @customer: cancel order', () => {
        // Enable refunds
        cy.loginViaApi().then(() => {
            cy.visit('/admin#/sw/settings/cart/index');
            cy.contains('Enable refunds').click();
            cy.get('.sw-settings-cart__save-action').click();
            cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
        });

        // Login
        cy.visit('/account/order');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        cy.get('.order-table-header-context-menu').click();
        cy.get('.dropdown-menu > [type="button"]').click();
        cy.get('form > .btn-primary').click();

        cy.get('.order-table-header-order-status').contains('Cancelled');
    });

    it('@base @customer: change payment', () => {
        // Login
        cy.visit('/account/order');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // change payment
        cy.get('.order-table').should('be.visible');
        cy.get('.order-table-header-order-table-body > :nth-child(3)').contains('Invoice');
        cy.get('.order-table-header-context-menu').click();
        cy.get('a.order-table-header-context-menu-content-link').click();
        cy.get('.confirm-payment .card-body > [data-toggle="modal"]').click();
        cy.get('label[for~="paymentMethod2"]').click();
        cy.get('#confirmPaymentForm > .btn-primary').click();
        cy.get('#confirmOrderForm > .btn').scrollIntoView();
        cy.get('#confirmOrderForm > .btn').click();
        cy.get('.finish-order-details .checkout-card .card-body p:first').contains('Paid in advance');
    });
});
