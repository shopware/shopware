
// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Account: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createCustomerFixture();
            });
    });

    it('@visual: check appearance of basic account address workflow', () => {
        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').type('test@example.com');
        cy.get('#loginPassword').type('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Add address form
        cy.get('.account-content .account-aside-item[title="Addresses"]')
            .should('be.visible')
            .click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Customer] Address overview', null, { widths: [375, 1920] });

        cy.get('a[href="/account/address/create"]').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Customer] Address - Create address modal', '.account-address-form', { widths: [375, 1920] });
    });
});
