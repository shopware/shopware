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
});
