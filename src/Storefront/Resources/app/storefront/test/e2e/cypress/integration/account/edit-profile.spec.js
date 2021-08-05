import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Edit profile', () => {
    beforeEach(() => {
        return cy.createCustomerFixtureStorefront();
    });

    it('@base @customer: Update profile', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true
                    }
                }
            };
            return cy.request(requestConfig);
        });

        cy.visit('/account/login');

        cy.window().then((win) => {
            const page = new AccountPageObject();

            // Login
            cy.get('.login-card').should('be.visible');
            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

            cy.get('.account-welcome h1').should((element) => {
                expect(element).to.contain('Overview');
            });

            cy.visit('/account/profile');
            const accountTypeSelector = 'select[name="accountType"]';
            const companySelector = 'input[name="company"]';
            const vatIdsSelector = 'input#vatIds';
            cy.get(accountTypeSelector).should('be.visible');

            cy.get(accountTypeSelector).typeAndSelect('Private');
            cy.get(companySelector).should('not.be.visible');
            cy.get(vatIdsSelector).should('not.be.visible');

            cy.get(accountTypeSelector).typeAndSelect('Commercial');
            cy.get(companySelector).should('be.visible');
            cy.get(companySelector).type('Company Testing');

            cy.get(vatIdsSelector).should('be.visible');
            cy.get(vatIdsSelector).type('vat-id-test');

            cy.get('#profilePersonalForm button[type="submit"]').click();

            cy.get('.alert-success .alert-content').contains('Profile has been updated.');
        });
    });
});
