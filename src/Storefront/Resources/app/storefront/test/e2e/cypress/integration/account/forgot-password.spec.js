import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Forgot password', () => {
    beforeEach(() => {
        return cy.createCustomerFixture()
    });

    it('Request a new password with existing customer mail', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.loginCard).should('be.visible');

        cy.get('.login-password-recover a').click();
        cy.get('input[name="email[email]"]').type('test@example.com');
        cy.get('.account-recover-password-submit').click();

        cy.get('.alert.alert-success').should('be.visible');
        cy.get('.alert.alert-success').should((element) => {
            expect(element).to.contain('We sent you a confirmation email, if the provided email address is registered.');
        });
    });

    it('Request a new password without existing customer mail', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.loginCard).should('be.visible');

        cy.get('.login-password-recover a').click();
        cy.get('input[name="email[email]"]').type('non-existing@mail.net');
        cy.get('.account-recover-password-submit').click();

        cy.get('.alert.alert-success').should('be.visible');

        // The success message should always be shown for security reasons
        cy.get('.alert.alert-success').should((element) => {
            expect(element).to.contain('We sent you a confirmation email, if the provided email address is registered.');
        });
    });
});
