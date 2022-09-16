import AccountPageObject from '../../../../support/pages/account.page-object';

// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Account - Login: Visual tests', () => {
    beforeEach(() => {
        cy.createCustomerFixture();
    });

    it('@visual: check appearance of basic storefront login workflow', { tags: ['pa-customers-orders'] }, () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        // Take snapshot for visual testing
        cy.takeSnapshot('[Account] Overview after login', '.account-overview', { widths: [375, 1920] });
    });
});
