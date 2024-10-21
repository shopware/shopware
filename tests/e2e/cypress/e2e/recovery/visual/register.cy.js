import AccountPageObject from '../../../support/pages/account.page-object';

describe('Account - Register: Visual tests', () => {

    it('@update: Check register workflow', { tags: ['pa-services-settings'] }, () => {
        const page = new AccountPageObject();

        cy.visit('/account/login');
        cy.get('.register-card').should('be.visible');
        const country = Cypress.env('locale') === 'en-GB' ? 'United Kingdom' : 'Deutschland';
        cy.get('select[name="billingAddress[countryId]"]').select(country);
        cy.get('.register-billing > .country-and-state-form-elements > .d-none').should('not.exist');
        cy.get('#billingAddressAddressCountryState').should('be.visible');

        // Take snapshot for visual testing
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Registration`, page.elements.registerCard, { widths: [375, 1920] });

        const salutation = Cypress.env('locale') === 'en-GB' ? 'Mr.' : 'Herr';
        cy.get('select[name="salutationId"]').select(salutation);
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

        cy.get('.register-card input[name="email"]').type('john-doe-for-testing1@example.com');
        cy.get('.register-card input[name="password"]').type('1234567890');

        cy.get('input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('input[name="billingAddress[city]"]').type('Anytown');

        cy.get('select[name="billingAddress[countryId]"]').select(country);

        cy.get('.register-submit .btn[type="submit"]').click();

        const header = Cypress.env('locale') === 'en-GB' ? 'Overview' : 'Übersicht';
        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain(header);
        });
    });
});
