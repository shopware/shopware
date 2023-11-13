import AccountPageObject from '../../../support/pages/account.page-object';
import SettingsPageObject from "../../../support/pages/module/sw-settings.page-object";

/**
 * @package checkout
 */
describe('Account: Register via account menu', () => {
    it('@login: Trigger validation error', { tags: ['pa-customers-orders'] }, () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        cy.get('[name="email"]:invalid').should('be.visible');
        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();
    });

    it('@base @login: Fill registration form and submit', { tags: ['pa-customers-orders'] }, () => {
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

        cy.get('select[name="billingAddress[countryId]"]').select('United States of America');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });

    it('@base @login: Register commercial customer with different address', { tags: ['pa-customers-orders'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
                    },
                },
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
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false,
                    },
                },
            };

            return cy.request(requestConfig);
        });
    });

    it('@base @login: Company field at different commercial address section should be required', { tags: ['pa-customers-orders'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
                    },
                },
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
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false,
                    },
                },
            };

            return cy.request(requestConfig);
        });
    });

    it('@base @login: Register commercial customer with different commercial address', { tags: ['pa-customers-orders'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
                    },
                },
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
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false,
                    },
                },
            };

            return cy.request(requestConfig);
        });
    });

    it('@base @login: Register non-commercial customer with different commercial address', { tags: ['pa-customers-orders'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
                    },
                },
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
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': false,
                    },
                },
            };

            return cy.request(requestConfig);
        });
    });

    it('@login: Fill registration without state', { tags: ['pa-customers-orders'] }, () => {
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

        cy.get('select[name="billingAddress[countryId]"]').select('United States of America');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });

    it('@login: Fill registration form as commercial user', { tags: ['pa-customers-orders'] }, () => {
        // activate the showAccountTypeSelection
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
                    },
                },
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

        cy.get('select[name="billingAddress[countryId]"]').select('United States of America');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });

    it('@registration: Trigger validation error with account type selection', { tags: ['pa-customers-orders'] }, () => {
        cy.window().then(() => {
            cy.authenticate().then((result) => {
                const requestConfig = {
                    headers: {
                        Authorization: `Bearer ${result.access}`,
                    },
                    method: 'POST',
                    url: `api/_action/system-config/batch`,
                    body: {
                        null: {
                            'core.loginRegistration.showAccountTypeSelection': true,
                        },
                    },
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

    it('@registration @package: Check non-default registration fields and allow customer deletion', { tags: ['pa-customers-orders'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showTitleField': true,
                        'core.loginRegistration.requireEmailConfirmation': true,
                        'core.loginRegistration.requirePasswordConfirmation': true,
                        'core.loginRegistration.showPhoneNumberField': true,
                        'core.loginRegistration.phoneNumberFieldRequired': true,
                        'core.loginRegistration.showBirthdayField': true,
                        'core.loginRegistration.birthdayFieldRequired': true,
                        'core.loginRegistration.showAdditionalAddressField1': true,
                        'core.loginRegistration.additionalAddressField1Required': true,
                        'core.loginRegistration.showAdditionalAddressField2': true,
                        'core.loginRegistration.additionalAddressField2Required': true,
                        'core.loginRegistration.requireDataProtectionCheckbox': true,
                        'core.loginRegistration.allowCustomerDeletion': true,
                    },
                },
            };
            return cy.request(requestConfig);
        });

        const page = new AccountPageObject();

        // Register customer
        cy.visit('/account/register');
        cy.get(page.elements.registerCard).should('be.visible');
        cy.get(`${page.elements.registerForm} [name="title"]`).typeAndCheckStorefront('Prof. Dr.');
        cy.get(`${page.elements.registerForm} [name="salutationId"]`).select('Mr.');
        cy.get(`${page.elements.registerForm} [name="firstName"]`).typeAndCheckStorefront('John');
        cy.get(`${page.elements.registerForm} [name="lastName"]`).typeAndCheckStorefront('Doe');
        cy.get(`${page.elements.registerForm} [for="personalBirthday"]`).contains('*').should('be.visible');
        cy.get(`${page.elements.registerForm} [name="birthdayDay"]`).select('14');
        cy.get(`${page.elements.registerForm} [name="birthdayMonth"]`).select('11');
        cy.get(`${page.elements.registerForm} [name="birthdayYear"]`).select('1988');
        cy.get(`${page.elements.registerForm} [name="email"]`).typeAndCheckStorefront('test@example.com');
        cy.get(`${page.elements.registerForm} #personalMailConfirmation`).typeAndCheckStorefront('test@example.com');
        cy.get(`${page.elements.registerForm} [name="password"]`).typeAndCheckStorefront('123456789');
        cy.get(`${page.elements.registerForm} #personalPasswordConfirmation`).typeAndCheckStorefront('123456789');
        cy.get(`${page.elements.registerForm} #billingAddressAddressStreet`).typeAndCheckStorefront('Ansgarstr 4');
        cy.get(`${page.elements.registerForm} #billingAddressAddressZipcode`).typeAndCheckStorefront('49134');
        cy.get(`${page.elements.registerForm} #billingAddressAddressCity`).typeAndCheckStorefront('Wallenhorst');
        cy.get(`${page.elements.registerForm} [for="billingAddressAdditionalField1"]`).contains('*').should('be.visible');
        cy.get(`${page.elements.registerForm} #billingAddressAdditionalField1`).typeAndCheckStorefront('Address line 1');
        cy.get(`${page.elements.registerForm} [for="billingAddressAdditionalField2"]`).contains('*').should('be.visible');
        cy.get(`${page.elements.registerForm} #billingAddressAdditionalField2`).typeAndCheckStorefront('Address line 2');
        cy.get(`${page.elements.registerForm} [for="billingAddressAddressPhoneNumber"]`).contains('*').should('be.visible');
        cy.get(`${page.elements.registerForm} #billingAddressAddressCountry`).select('Germany');
        cy.get(`${page.elements.registerForm} #billingAddressAddressCountryState`).select('Berlin');
        cy.get(`${page.elements.registerForm} #billingAddressAddressPhoneNumber`).typeAndCheckStorefront('0123456789');
        cy.get(`${page.elements.registerForm} .data-protection-information input[type="checkbox"]`).click({ force: true });
        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        // Verify registration
        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');
        cy.get('.account-aside [title="Your profile"]').click();
        cy.url().should('include', '/profile');

        // Delete account
        cy.get('.account-deleting.py-4').contains('delete all your personal data').should('be.visible');
        cy.get('.account-deleting.py-4 > a').click();
        cy.get('#confirmDeleteAccountModal  .modal-content').should('be.visible');
        cy.get('.btn.btn-outline-danger').click();
        cy.url().should('not.include', '/account');
        cy.get('.alert-content').contains('account was deleted').should('be.visible');
    });

    it('@registration @package: Trigger validation error with short password', { tags: ['pa-customers-orders'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.passwordMinLength': 9,
                    },
                },
            };
            return cy.request(requestConfig);
        });

        const page = new AccountPageObject();

        // Register customer with short password
        cy.visit('/account/register');
        cy.get(page.elements.registerCard).should('be.visible');
        cy.get(`${page.elements.registerForm} [name="salutationId"]`).select('Mr.');
        cy.get(`${page.elements.registerForm} [name="firstName"]`).typeAndCheckStorefront('John');
        cy.get(`${page.elements.registerForm} [name="lastName"]`).typeAndCheckStorefront('Doe');
        cy.get(`${page.elements.registerForm} [name="email"]`).typeAndCheckStorefront('test@example.com');
        cy.get(`${page.elements.registerForm} [name="password"]`).typeAndCheckStorefront('12345678');
        cy.get(`${page.elements.registerForm} #billingAddressAddressStreet`).typeAndCheckStorefront('Ansgarstr 4');
        cy.get(`${page.elements.registerForm} #billingAddressAddressZipcode`).typeAndCheckStorefront('49134');
        cy.get(`${page.elements.registerForm} #billingAddressAddressCity`).typeAndCheckStorefront('Wallenhorst');
        cy.get(`${page.elements.registerForm} #billingAddressAddressCountry`).select('Germany');
        cy.get(`${page.elements.registerForm} #billingAddressAddressCountryState`).select('Berlin');
        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        // Verify registration isn't completed
        cy.url().should('include', '/register');
        cy.get('[data-form-validation-length-text]').contains('9').should('be.visible');
    });

    it('@base @login: Register commercial customer with country state required', { tags: ['pa-customers-orders'] }, async () => {
        const data = {
            id: '00000000000000000000000000000001',
            name: 'SalesChannel #1',
            accessKey: 'SWSCWLRRZJR2ZE05VMYYVGT1W1',
        };

        cy.searchViaAdminApi({
            endpoint: 'payment-method',
            data: {
                field: 'name',
                value: 'Invoice',
            },
        }).then((paymentMethod) => {
            data.paymentMethodId = paymentMethod.id;

            return cy.searchViaAdminApi({
                endpoint: 'shipping-method',
                data: {
                    field: 'name',
                    value: 'Standard',
                },
            });
        }).then((shippingMethod) => {
            data.shippingMethodId = shippingMethod.id;

            return cy.searchViaAdminApi({
                endpoint: 'category',
                data: {
                    field: 'name',
                    value: 'Home',
                },
            });
        }).then((category) => {
            data.navigationCategoryId = category.id;

            return cy.searchViaAdminApi({
                endpoint: 'country',
                data: {
                    field: 'iso3',
                    value: 'USA',
                },
            });
        }).then((country) => {
            data.countryId = country.id;

            return cy.createDefaultFixture('sales-channel', data);
        });

        let page = new SettingsPageObject();

        cy.intercept({
            url: '/api/_action/theme/**/assign/**',
            method: 'POST',
        }).as('themeAssign');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'PATCH',
        }).as('saveCountry');

        cy.intercept({
            method: 'POST',
            url: '/api/search/country',
        }).as('searchCountries');

        cy.setSalesChannelDomain('SalesChannel #1');

        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.goToSalesChannelDetail('SalesChannel #1');

        cy.contains('.sw-tabs-item', 'Theme').click();

        cy.contains('Theme assignment').should('be.visible');
        cy.contains('Assign theme').click();

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-theme-modal__content-item').contains('Shopware default theme').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Change theme').should('be.visible');
        cy.contains('.sw-button--primary','Change theme').click();

        // Ensure theme assignment request is successful
        cy.wait('@themeAssign').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Shopware default theme');

        cy.goToSalesChannelDetail('SalesChannel #1')
            .selectCountryForSalesChannel('Germany')
            .selectLanguageForSalesChannel('Deutsch')
            .selectCurrencyForSalesChannel('US-Dollar');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);

        // assert that there is an available list of countries
        cy.get(`${page.elements.countryListContent}`).should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.smart-bar__header', 'Countries');

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');
        cy.get('input.sw-search-bar__input').type('{esc}');

        cy.wait('@searchCountries');

        // choose "Germany"
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName} a`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.countryColumnName} a`).click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // choose "state tab"
        cy.get('.sw-settings-country__address-handling-tab').click();

        cy.get('.sw-settings-country-address-handling__option-items:first-of-type')
            .find('.sw-field--switch__input input[name=sw-field--country-forceStateInRegistration]')
            .check({ force: true });

        cy.get('.sw-button-process__content').click();

        cy.wait('@saveCountry').its('response.statusCode').should('equal', 204);

        cy.visit('/de/account/login');

        page = new AccountPageObject();

        cy.get('select[name="salutationId"]').select('Frau');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get('#personalMail').type('testvat@gmail.com');
        cy.get('#personalPassword').type('password@123456');

        cy.get('#billingAddressAddressStreet').type('ABC Ansgarstr 4');
        cy.get('#billingAddressAddressZipcode').type('49134');
        cy.get('#billingAddressAddressCity').type('Wallenhorst');
        cy.get('#billingAddressAddressCountryState').select('Hamburg');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Übersicht');
        });
    });
});
