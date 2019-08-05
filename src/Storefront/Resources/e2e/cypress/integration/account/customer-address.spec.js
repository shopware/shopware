import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Login as customer', () => {
    beforeEach(() => {
        return cy.createCustomerFixture()
    });

    it.skip('Add new address and swap roles of these two addresses', () => {
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
        cy.get('.account-content .account-aside-item[href="/account"]').click();
        cy.get(`${page.elements.lightButton}[title="Change shipping address"]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('be.visible');
        cy.get('#addressEditorAccordion .address-editor-card p').contains('Sherman');
        cy.get('#addressEditorAccordion .address-editor-card p').contains('42 Wallaby Way');

        // Set new address as shipping address
        cy.get(`${page.elements.lightButton}[title="Set as default shipping "]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('not.exist');
        // TODO: After saving the modal there is no success notification. This should be added!
        cy.reload();

        cy.get('.overview-shipping-address p').contains('Sherman');
        cy.get(`${page.elements.lightButton}[title="Change shipping address"]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('be.visible');
        cy.get('.address-editor-modal .icon-x').click();

        // Swap shipping and billing address
        cy.get(`${page.elements.lightButton}[title="Change billing address"]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('be.visible');
        cy.get(`${page.elements.lightButton}[title="Set as default billing"]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('not.exist');
        // TODO: After saving the modal there is no success notification. This should be added!
        cy.reload();

        cy.get(`${page.elements.lightButton}[title="Change shipping address"]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('be.visible');
        cy.get(`${page.elements.lightButton}[title="Set as default shipping "]`).click();
        cy.get('.address-editor-modal .modal-dialog').should('not.exist');
        // TODO: After saving the modal there is no success notification. This should be added!
        cy.reload();

        cy.get('.overview-billing-address p').contains('Sherman');
        cy.get('.overview-shipping-address p').contains('Eroni');
    });
});
