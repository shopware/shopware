import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Login as customer', () => {
    beforeEach(() => {
        return cy.createCustomerFixtureStorefront()
    });

    it('@login: Login with wrong credentials', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('Anything');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.alert-danger').should((element) => {
            expect(element).to.contain('There is no account that matches the given credentials.');
        });
    });

    it('@package @login: Login with valid credentials', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });
});
