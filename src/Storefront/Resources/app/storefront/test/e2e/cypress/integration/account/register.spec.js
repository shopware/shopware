import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Register via account menu', () => {
    it('@login: Trigger validation error', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        cy.get('[name="email"]:invalid').should('be.visible');
        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();
    });

    it('@base @login: Fill registration form and submit', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get(`${page.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');
        cy.get(`${page.elements.registerForm} input[name="password"]`).type('1234567890');

        cy.get('input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('input[name="billingAddress[city]"]').type('Anytown');

        cy.get('select[name="billingAddress[countryId]"]').select('USA');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });

    it('@login: Fill registration without state', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get(`${page.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');
        cy.get(`${page.elements.registerForm} input[name="password"]`).type('1234567890');

        cy.get('input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('input[name="billingAddress[city]"]').type('Anytown');

        cy.get('select[name="billingAddress[countryId]"]').select('USA');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });
});
