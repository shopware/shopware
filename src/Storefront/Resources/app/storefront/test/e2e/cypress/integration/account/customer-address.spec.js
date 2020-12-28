import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Handle addresses as customer', () => {
    beforeEach(() => {
        return cy.createCustomerFixtureStorefront()
    });

    it('@base @customer: Add new address and swap roles of these two addresses', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Add address form
        cy.get('.account-content .account-aside-item[title="Addresses"]')
            .should('be.visible')
            .click();

        cy.get('a[href="/account/address/create"]').click();
        cy.get('.account-address-form').should('be.visible');

        // Fill in and submit address
        cy.get('#addresspersonalSalutation').typeAndSelect('Mr.');
        cy.get('#addresspersonalFirstName').typeAndCheckStorefront('P.  ');
        cy.get('#addresspersonalLastName').typeAndCheckStorefront('Sherman');
        cy.get('#addressAddressStreet').typeAndCheckStorefront('42 Wallaby Way');
        cy.get('#addressAddressZipcode').typeAndCheckStorefront('2000');
        cy.get('#addressAddressCity').typeAndCheckStorefront('Sydney');
        cy.get('#addressAddressCountry').typeAndSelect('Australia');
        cy.get('.address-form-submit').click();

        // Verify new address
        cy.get('.alert-success .alert-content').contains('Address has been saved.');

        // Set new address as shipping address
        cy.contains('Set as default shipping').click();
        cy.get('.shipping-address p').contains('Sherman');

        // Swap shipping and billing address
        cy.contains('Set as default billing').click();
        cy.contains('Set as default shipping').click();

        cy.get('.billing-address p').contains('Sherman');
        cy.get('.shipping-address p').contains('Eroni');
    });

    it('@base @customer: Add new address with account type commercial', () => {
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

        cy.visit('/account/login');

        cy.window().then((win) => {

            // Login
            cy.get('.login-card').should('be.visible');
            cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
            cy.get('#loginPassword').typeAndCheckStorefront('shopware');
            cy.get('.login-submit [type="submit"]').click();

            // Add address form
            cy.get('.account-content .account-aside-item[title="Addresses"]')
                .should('be.visible')
                .click();

            cy.get('a[href="/account/address/create"]').click();
            cy.get('.account-address-form').should('be.visible');

            const accountTypeSelector = 'select[name="address[accountType]"]';
            const billingAddressCompanySelector = '#addresscompany';
            const billingAddressDepartmentSelector = '#addressdepartment';

            cy.get(accountTypeSelector).should('be.visible');
            cy.get(accountTypeSelector).typeAndSelect('Private');
            cy.get(billingAddressCompanySelector).should('not.be.visible');
            cy.get(billingAddressDepartmentSelector).should('not.be.visible');

            // Fill in and submit address
            cy.get('#addresspersonalSalutation').typeAndSelect('Mr.');
            cy.get('#addresspersonalFirstName').typeAndCheckStorefront('P.  ');
            cy.get('#addresspersonalLastName').typeAndCheckStorefront('Sherman');
            cy.get('#addressAddressStreet').typeAndCheckStorefront('42 Wallaby Way');
            cy.get('#addressAddressZipcode').typeAndCheckStorefront('2000');
            cy.get('#addressAddressCity').typeAndCheckStorefront('Sydney');
            cy.get('#addressAddressCountry').typeAndSelect('Australia');

            cy.get(accountTypeSelector).typeAndSelect('Commercial');
            cy.get(billingAddressCompanySelector).should('be.visible');
            cy.get(billingAddressCompanySelector).type('Company Testing');
            cy.get(billingAddressDepartmentSelector).should('be.visible')
            cy.get(billingAddressDepartmentSelector).type('Department Testing');

            cy.get('.address-form-submit').click();

            // Verify new address
            cy.get('.alert-success .alert-content').contains('Address has been saved.');

            // Swap shipping and billing address
            cy.contains('Set as default billing').click();
            cy.contains('Set as default shipping').click();

            cy.get('.billing-address p').contains('Company Testing - Department Testing');
            cy.get('.shipping-address p').contains('Company Testing - Department Testing');
        });
    });
});
