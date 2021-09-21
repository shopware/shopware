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
                method: 'POST',
                url: `api/_action/system-config/batch`,
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
        const accountTypeSelectorForDifferentAddress = 'select[name="shippingAddress[accountType]"]';

        cy.get(accountTypeSelector).should('be.visible');
        cy.get(accountTypeSelector).typeAndSelect('Commercial');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get('#billingAddresscompany').type('ABC Company');
        cy.get('#billingAddressdepartment').type('ABC Department');
        cy.get('#personalMail').type('testvat@gmail.com');
        cy.get('#personalPassword').type('password@123456');

        cy.get('#billingAddressAddressStreet').type('Ansgarstr 4');
        cy.get('#billingAddressAddressZipcode').type('49134');
        cy.get('#billingAddressAddressCity').type('Wallenhorst');
        cy.get('#billingAddressAddressCountry').select('Germany');
        cy.get('#billingAddressAddressCountryState').select('Berlin');

        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

        cy.get(accountTypeSelectorForDifferentAddress).should('be.visible');
        cy.get(accountTypeSelectorForDifferentAddress).typeAndSelect('Private');

        cy.get('#shippingAddresspersonalSalutation').select('Mr.');
        cy.get('#shippingAddresspersonalFirstName').type('John');
        cy.get('#shippingAddresspersonalLastName').type('Doe');
        cy.get('#shippingAddressAddressStreet').type('Ansgarstr 20');
        cy.get('#shippingAddressAddressZipcode').type('12345');
        cy.get('#shippingAddressAddressCity').type('Newland');
        cy.get('#shippingAddressAddressCountry').select('Germany');

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
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false
                    }
                }
            };

            return cy.request(requestConfig);
        });
    });

    it('@base @login: Company field at different commercial address section should be required', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
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
        const accountTypeSelectorForDifferentAddress = 'select[name="shippingAddress[accountType]"]';

        cy.get(accountTypeSelector).should('be.visible');

        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

        cy.get(accountTypeSelectorForDifferentAddress).should('be.visible');
        cy.get(accountTypeSelectorForDifferentAddress).typeAndSelect('Commercial');

        cy.get('#shippingAddresscompany').should('be.visible');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.get('#shippingAddresscompany:invalid').should('be.visible');

        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false
                    }
                }
            };

            return cy.request(requestConfig);
        });
    });

    it('@base @login: Register commercial customer with different commercial address', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
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
        const accountTypeSelectorForDifferentAddress = 'select[name="shippingAddress[accountType]"]';

        cy.get(accountTypeSelector).should('be.visible');
        cy.get(accountTypeSelector).typeAndSelect('Commercial');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get('#billingAddresscompany').type('Company ABC');
        cy.get('#billingAddressdepartment').type('ABC Department');
        cy.get('#personalMail').type('testvat@gmail.com');
        cy.get('#personalPassword').type('password@123456');

        cy.get('#billingAddressAddressStreet').type('ABC Ansgarstr 4');
        cy.get('#billingAddressAddressZipcode').type('49134');
        cy.get('#billingAddressAddressCity').type('Wallenhorst');
        cy.get('#billingAddressAddressCountry').select('Germany');
        cy.get('#billingAddressAddressCountryState').select('Berlin');

        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

        cy.get(accountTypeSelectorForDifferentAddress).should('be.visible');
        cy.get(accountTypeSelectorForDifferentAddress).typeAndSelect('Commercial');

        cy.get('#billingAddresscompany').should('be.visible');

        cy.get('#shippingAddresspersonalSalutation').select('Mr.');
        cy.get('#shippingAddresspersonalFirstName').type('John');
        cy.get('#shippingAddresspersonalLastName').type('Doe');
        cy.get('#shippingAddresscompany').type('Company XYZ');
        cy.get('#shippingAddressdepartment').type('XYZ Department');
        cy.get('#shippingAddressAddressStreet').type('XYZ Ansgarstr 20');
        cy.get('#shippingAddressAddressZipcode').type('67890');
        cy.get('#shippingAddressAddressCity').type('Newland');
        cy.get('#shippingAddressAddressCountry').select('Germany');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        cy.get('.js-account-overview-addresses').contains('Company ABC - ABC Department');
        cy.get('.js-account-overview-addresses').contains('Company XYZ - XYZ Department');

        cy.visit('/account/profile');
        cy.get(`.account-profile-personal ${accountTypeSelector}`).should('have.value', 'business');

        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false
                    }
                }
            };

            return cy.request(requestConfig);
        });
    });

    it('@base @login: Register non-commercial customer with different commercial address', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
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
        const accountTypeSelectorForDifferentAddress = 'select[name="shippingAddress[accountType]"]';

        cy.get(accountTypeSelector).should('be.visible');
        cy.get(accountTypeSelector).typeAndSelect('Private');

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get('#personalMail').type('testvat@gmail.com');
        cy.get('#personalPassword').type('password@123456');

        cy.get('#billingAddressAddressStreet').type('ABC Ansgarstr 4');
        cy.get('#billingAddressAddressZipcode').type('49134');
        cy.get('#billingAddressAddressCity').type('Wallenhorst');
        cy.get('#billingAddressAddressCountry').select('Germany');
        cy.get('#billingAddressAddressCountryState').select('Berlin');

        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

        cy.get(accountTypeSelectorForDifferentAddress).should('be.visible');
        cy.get(accountTypeSelectorForDifferentAddress).typeAndSelect('Commercial');

        cy.get('#billingAddresscompany').should('not.be.visible');

        cy.get('#shippingAddresspersonalSalutation').select('Mr.');
        cy.get('#shippingAddresspersonalFirstName').type('John');
        cy.get('#shippingAddresspersonalLastName').type('Doe');
        cy.get('#shippingAddresscompany').type('Company XYZ');
        cy.get('#shippingAddressdepartment').type('XYZ Department');
        cy.get('#shippingAddressAddressStreet').type('XYZ Ansgarstr 20');
        cy.get('#shippingAddressAddressZipcode').type('67890');
        cy.get('#shippingAddressAddressCity').type('Newland');
        cy.get('#shippingAddressAddressCountry').select('Germany');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        cy.get('.js-account-overview-addresses').contains('Company XYZ - XYZ Department');

        cy.visit('/account/profile');
        cy.get(`.account-profile-personal ${accountTypeSelector}`).should('have.value', 'private');

        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
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
                method: 'POST',
                url: `api/_action/system-config/batch`,
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
        cy.window().then((win) => {
            cy.authenticate().then((result) => {
                const requestConfig = {
                    headers: {
                        Authorization: `Bearer ${result.access}`
                    },
                    method: 'POST',
                    url: `api/_action/system-config/batch`,
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

            cy.get('#billingAddresscompany').type('ABC Company');
            cy.get('#billingAddressdepartment').type('ABC Department');
            cy.get('#vatIds').type('ABC-VAT-ID');
            cy.get('#billingAddressAddressStreet').type('Ansgarstr 4');
            cy.get('#billingAddressAddressZipcode').type('49134');
            cy.get('#billingAddressAddressCity').type('Wallenhorst');
            cy.get('#billingAddressAddressCountry').select('Germany');
            cy.get('#billingAddressAddressCountryState').select('Berlin');

            cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();
        });
    });
});
