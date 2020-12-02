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

    it('@base @login: Register commercial customer with different address', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/${Cypress.env('apiVersion')}/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true
                    }
                }
            };

            return cy.request(requestConfig);
        });

        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        const accountTypeSelector = 'select[name="accountType"]';
        cy.get(accountTypeSelector).should('be.visible');
        cy.get(accountTypeSelector).typeAndSelect('Commercial');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get(`${page.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');
        cy.get(`${page.elements.registerForm} input[name="password"]`).type('1234567890');

        cy.get('.register-billing input[name="billingAddress[company]"]').type('ABC Company');
        cy.get('.register-billing input[name="billingAddress[department]"]').type('ABC Department');
        cy.get('.register-billing input[name="billingAddress[vatId]"]').type('ABC-VAT-ID');
        cy.get('.register-billing input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('.register-billing input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('.register-billing input[name="billingAddress[city]"]').type('Anytown');
        cy.get('.register-billing select[name="billingAddress[countryId]"]').select('USA');
        cy.get('.register-billing select[name="billingAddress[countryStateId]"').should('be.visible');
        cy.get('.register-billing select[name="billingAddress[countryStateId]"]').select('Ohio');

        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

        cy.get('.register-shipping select[name="shippingAddress[salutationId]"]').select('Mr.');
        cy.get('.register-shipping input[name="shippingAddress[firstName]"]').type('John');
        cy.get('.register-shipping input[name="shippingAddress[lastName]"]').type('Doe');
        cy.get('.register-shipping input[name="shippingAddress[company]"]').type('ABC Company');
        cy.get('.register-shipping input[name="shippingAddress[department]"]').type('ABC Department');
        cy.get('.register-shipping input[name="shippingAddress[vatId]"]').type('ABC-VAT-ID');
        cy.get('.register-shipping input[name="shippingAddress[street]"]').type('123 Main St');
        cy.get('.register-shipping input[name="shippingAddress[zipcode]"]').type('9876');
        cy.get('.register-shipping input[name="shippingAddress[city]"]').type('Anytown');
        cy.get('.register-shipping select[name="shippingAddress[countryId]"]').select('USA');
        cy.get('.register-shipping select[name="shippingAddress[countryStateId]"').should('be.visible');
        cy.get('.register-shipping select[name="shippingAddress[countryStateId]"]').select('Ohio');

        cy.get('.register-shipping input[name="accountType"]').should('not.exist');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        cy.visit('/account/profile');
        cy.get(`.account-profile-personal ${accountTypeSelector}`).should('have.value', 'business');

        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/${Cypress.env('apiVersion')}/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false
                    }
                }
            };

            return cy.request(requestConfig);
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

    it('@login: Fill registration form as commercial user', () => {
        // activate the showAccountTypeSelection
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/${Cypress.env('apiVersion')}/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true
                    }
                }
            };

            return cy.request(requestConfig);
        });

        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        cy.get('#accountType').select('Commercial');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get(`${page.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');
        cy.get(`${page.elements.registerForm} input[name="password"]`).type('1234567890');

        cy.get('#billingAddresscompany').type('Shopware AG');

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

    it('@registration: Trigger validation error with account type selection', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/${Cypress.env('apiVersion')}/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true
                    }
                }
            };

            return cy.request(requestConfig);
        });

        cy.createCustomerFixtureStorefront();

        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        const accountTypeSelector = `${page.elements.registerForm} select[name="accountType"]`;
        cy.get(accountTypeSelector).should('be.visible');
        cy.get(accountTypeSelector).typeAndSelect('Commercial');

        cy.get(`${page.elements.registerForm} select[name="salutationId"]`).select('Mr.');
        cy.get(`${page.elements.registerForm} input[name="firstName"]`).type('John');
        cy.get(`${page.elements.registerForm} input[name="lastName"]`).type('Doe');
        cy.get(`${page.elements.registerForm} input[name="email"]`).type('test@example.com');
        cy.get(`${page.elements.registerForm} input[name="password"]`).type('1234567890');

        cy.get('.register-address input[name="billingAddress[company]"]').type('Test Company');
        cy.get('.register-address input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('.register-address input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('.register-address input[name="billingAddress[city]"]').type('Anytown');

        cy.get('.register-address select[name="billingAddress[countryId]"]').select('USA');
        cy.get('.register-address select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();
    });
});
