// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Account - Order: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            // freezes the system time to Jan 1, 2018
            const now = new Date(2018, 1, 1);
            cy.clock(now);
        }).then(() => {
            return cy.setShippingMethodInSalesChannel('Standard');
        }).then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'product',
                    data: {
                        field: 'name',
                        value: 'Product name'
                    }
                });
            })
            .then((result) => {
                return cy.createOrder(result.id, {
                    username: 'test@example.com',
                    password: 'shopware'
                });
            });
    });

    it('@visual: check appearance of basic account order workflow', () => {
        // Login
        cy.visit('/account/order');
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').type('test@example.com');
        cy.get('#loginPassword').type('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.order-table-header-heading', 'color: #fff');
        cy.get('.order-table-header-heading')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Change visibility of the element to ensure consistent snapshots
        cy.changeElementStyling('.order-table-header-order-status', 'visibility: hidden');
        cy.get('.order-table-header-order-status')
            .should('have.css', 'visibility', 'hidden');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Account] Overview', '.order-table', { widths: [375, 1920] });
    });
});
