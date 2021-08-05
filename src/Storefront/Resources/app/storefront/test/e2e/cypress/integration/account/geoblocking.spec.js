import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: indicate non shippable country on register page', () => {
    beforeEach(() => {
        cy.onlyOnFeature('FEATURE_NEXT_15707');

        return cy.searchViaAdminApi({
            endpoint: 'country',
            data: {
                field: 'iso',
                value: 'DE'
            }
        }).then(result => {
            return cy.updateViaAdminApi('country', result.id, {
                data: {
                    shippingAvailable: false
                }
            });
        })
        .then(() => {
            return cy.createCustomerFixtureStorefront();
        });
    })

    it('@registration: Register with non shippable countries', () => {
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

        cy.get('select[name="billingAddress[countryId]"]').select('Germany (Delivery not possible)');
        cy.get('select[name="billingAddress[countryStateId]"').should('be.visible');

        cy.get('select[name="billingAddress[countryStateId]"]').select('Hamburg');

        cy.get(page.elements.registerCheckbox).check({ force: true });

        cy.get('select[name="shippingAddress[salutationId]"]').select('Mrs.');
        cy.get('input[name="shippingAddress[firstName]"]').type('Jane');
        cy.get('input[name="shippingAddress[lastName]"]').type('Doe');

        cy.get('input[name="shippingAddress[street]"]').type('123 Different St');
        cy.get('input[name="shippingAddress[zipcode]"]').type('9876');
        cy.get('input[name="shippingAddress[city]"]').type('Randotown');

        cy.get('select[name="shippingAddress[countryId]"]')
            .contains('Germany (Delivery not possible)')
            .should('be.disabled');

        cy.get('select[name="shippingAddress[countryId]"]').select('United Kingdom');
        cy.get('select[name="shippingAddress[countryStateId]"').should('be.visible');

        cy.get('select[name="shippingAddress[countryStateId]"]').select('Westminster');

        cy.get(`${page.elements.registerSubmit} [type="submit"]`).click();

        cy.url().should('not.include', '/register');
        cy.url().should('include', '/account');

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });

    it('User is not able to set new shipping address with a non-shippable country', () => {
        cy.server();
        cy.route({
            method: 'POST',
            url: '/country/country-state-data'
        }).as('countryStateRequest');

        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');

        cy.get('.login-submit [type="submit"]').click();

        cy.get('.account-overview .alert-warning')
            .contains('We can not deliver to the country that is stored in your delivery address.');

        cy.get('.overview-shipping-address > .card > .card-body > .card-actions > .btn').click();

        // create new address
        cy.get('.address-editor-modal').should('be.visible');

        cy.get('.row > :nth-child(2) > .btn').click();

        cy.get('#address-create-new #addresspersonalSalutation').select('Mr.');

        cy.get('#address-create-new #addresspersonalFirstName').typeAndCheck('John');

        cy.get('#address-create-new #addresspersonalLastName').typeAndCheck('Doe');

        cy.get('#address-create-new #addressAddressStreet').typeAndCheck('Test street 123.');

        cy.get('#address-create-new #addressAddressZipcode').typeAndCheck('000010');

        cy.get('#address-create-new #addressAddressCity').typeAndCheck('Test city');

        cy.get('#address-create-new #addressAddressCountry').select('Germany (Delivery not possible)');

        cy.wait('@countryStateRequest').then(xhr => {
            expect(xhr).to.have.property('status', 200)
        });

        cy.get('#address-create-new #addressAddressCountryState').select('Hamburg');

        cy.get('#address-create-new button[type="submit"]').click();

        cy.get('.address-editor-modal').should('not.be.visible');

        cy.get('.overview-shipping-address > .card > .card-body > .card-actions > .btn').click();

        cy.get('.address-editor-modal').should('be.visible');

        // waiting for this request to reduce flakyness of the test. Apparantely the modal gets reopened after it got closed because of this request.
        cy.wait('@countryStateRequest').then(xhr => {
            expect(xhr).to.have.property('status', 200)
        });

        cy.get('.address-editor-modal .card .alert')
            .contains('A delivery to this country is not possible.')
            .should('be.visible');

        cy.get('#address-list .alert')
            .contains('A delivery to this country is not possible.')
            .should('be.visible');

        cy.get('#address-list form[action="/widgets/account/address-book"] button')
            .contains('Set as default shipping address')
            .should('be.disabled');

        cy.get('.js-pseudo-modal .modal-close').click();

        cy.get('.address-editor-modal').should('not.be.visible');

        // check behaviour on address overview page
        cy.visit('/account/address');

        cy.get('.address-list .alert')
            .contains('A delivery to this country is not possible.')
            .should('be.visible');

        cy.get('.address-action-set-default-shipping')
            .contains('Set as default shipping address')
            .should('be.disabled');
    });
});
