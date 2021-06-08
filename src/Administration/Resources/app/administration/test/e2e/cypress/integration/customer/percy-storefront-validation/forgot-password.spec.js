import AccountPageObject from '../../../support/pages/account.page-object';

// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Account - Password: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createCustomerFixture();
            });
    });

    it('@visual: check appearance of password recovery workflow', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.loginCard).should('be.visible');

        cy.get('.login-password-recover a').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Account] Request password', '.account-recover-password-submit', { widths: [375, 1920] });
    });
});
