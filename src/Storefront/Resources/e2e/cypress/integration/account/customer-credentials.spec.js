import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Login as customer', () => {
    beforeEach(() => {
        return cy.createCustomerFixture()
    });

    it('Change email and log in', () => {
        const page = new AccountPageObject();

        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        // Open profile
        cy.get('.account-overview-card a[href="/account/profile"]').click();
        cy.get('h1').contains('Profile');

        // Change email
        cy.get('a[href="#profile-email-form"]').click();

        // Change passwort
        cy.get('#personalMail').type('tester@example.com');
        cy.get('#personalMailConfirmation').type('tester@example.com');
        cy.get('#personalMailPasswordCurrent').type('shopware');
        cy.get('#profileMailForm .profile-form-submit').click();
        cy.get('.alert-content').contains('Your email address has been updated.');

        // Verify change
        cy.get('.account-aside .icon-log-out').click();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheck('tester@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });

    it('Change password and log in', () => {
        const page = new AccountPageObject();

        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        // Open profile
        cy.get('.account-overview-card a[href="/account/profile"]').click();
        cy.get('h1').contains('Profile');

        // Change email
        cy.get('a[href="#profile-password-form"]').click();

        // Change passwort
        cy.get('#newPassword').type('demodemo');
        cy.get('#passwordConfirmation').type('demodemo');
        cy.get('#password').type('shopware');
        cy.get('#profilePasswordForm .profile-form-submit').click();
        cy.get('.alert-content').contains('Your password has been updated.');

        // Verify change
        cy.get('.account-aside .icon-log-out').click();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('demodemo');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });
});
