import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Login as customer', () => {
    beforeEach(() => {
        return cy.createCustomerFixture()
    });

    it('Add new address and swap roles of these two addresses', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Add address form
        cy.get('.account-content .account-aside-item[title="Addresses"]')
            .should('be.visible')
            .click();
        cy.get('a[href="/account/address/create"]').click();
        cy.get('.account-address-form').should('be.visible');

        // Fill in and submit address
        cy.get('#addresspersonalSalutation').typeAndCheckSelectField('Mr.');
        cy.get('#addresspersonalFirstName').typeAndCheck('P.  ');
        cy.get('#addresspersonalLastName').typeAndCheck('Sherman');
        cy.get('#addressAddressStreet').typeAndCheck('42 Wallaby Way');
        cy.get('#addressAddressZipcode').typeAndCheck('2000');
        cy.get('#addressAddressCity').typeAndCheck('Sydney');
        cy.get('#addressAddressCountry').typeAndCheckSelectField('Australia');
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
});
