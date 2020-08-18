describe('Account - Order: Visual tests', () => {
    beforeEach(() => {

        cy.setToInitialState().then(() => {
            return cy.createProductFixture()
        }).then(() => {
            return cy.createCustomerFixture()
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

    it('@visual: check appearance of basic account order workflow', () => {
        // Login
        cy.visit('/account/order');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Account overview', '.order-table');
    });

    it('@visual: check appearance of basic cancel order workflow', () => {
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

        // Take snapshot for visual testing
        cy.takeSnapshot('Cancel order - confirm', '.order-table');

        cy.get('.order-table-header-context-menu').click();
        cy.get('.dropdown-menu > [type="button"]').click();
        cy.get('form > .btn-primary').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Cancel order - finish', '.order-table-header-order-status');
    });
});
