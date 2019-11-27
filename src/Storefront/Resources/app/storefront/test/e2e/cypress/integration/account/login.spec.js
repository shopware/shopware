import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Login as customer', () => {
    beforeEach(() => {
        return cy.createCustomerFixture()
    });

    it('Login with wrong credentials', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('Anything');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.alert-danger').should((element) => {
            expect(element).to.contain('There is no account that matches the given credentials.');
        });
    });

    it('Login with valid credentials', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });
});
