import CheckoutPageObject from "../../support/pages/checkout.page-object";
import AccountPageObject from "../../support/pages/account.page-object";
let product = {};

describe(`Checkout as Guest`, () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_15957');
    });

    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category')
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            cy.visit('/account/login');
        });
    });

    it('@base @checkout: Edit VAT id when confirm order', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'post',
                url: `api/_action/system-config/batch`,
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
            const page = new CheckoutPageObject();
            const accountPage = new AccountPageObject();

            // Product detail
            cy.get('.header-search-input').should('be.visible');
            cy.get('.header-search-input').type(product.name);
            cy.get('.search-suggest-product-name').contains(product.name);
            cy.get('.search-suggest-product-price').contains(product.price[0].gross);
            cy.get('.search-suggest-product-name').click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(`${page.elements.offCanvasCart}.is-open`).should('be.visible');
            cy.get(`${page.elements.cartItem}-label`).contains(product.name);

            // Checkout
            cy.get('.offcanvas-cart-actions .btn-primary').click();

            cy.get(accountPage.elements.registerCard).should('be.visible');

            const accountTypeSelector = 'select[name="accountType"]';
            const billingAddressCompanySelector = 'input[name="billingAddress[company]"]';
            const billingAddressDepartmentSelector = 'input[name="billingAddress[department]"]';
            const vatIdsSelector = 'input#vatIds';
            cy.get(accountTypeSelector).should('be.visible');

            cy.get('select[name="salutationId"]').select('Mr.');
            cy.get('input[name="firstName"]').type('John');
            cy.get('input[name="lastName"]').type('Doe');

            cy.get(accountTypeSelector).typeAndSelect('Commercial');
            cy.get(billingAddressCompanySelector).should('be.visible');
            cy.get(billingAddressCompanySelector).type('Company Testing');
            cy.get(billingAddressDepartmentSelector).should('be.visible');
            cy.get(billingAddressDepartmentSelector).type('Department Testing');
            cy.get(vatIdsSelector).type('55555');

            cy.get(`${accountPage.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');
            cy.get('.register-guest-control.custom-checkbox label').scrollIntoView();
            cy.get('.register-guest-control.custom-checkbox label').click(1, 1);

            cy.get('input[name="billingAddress[street]"]').type('123 Main St');
            cy.get('input[name="billingAddress[zipcode]"]').type('9876');
            cy.get('input[name="billingAddress[city]"]').type('Anytown');

            cy.get('select[name="billingAddress[countryId]"]').select('USA');
            cy.get('select[name="billingAddress[countryStateId]"]').should('be.visible');
            cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');

            cy.get(`${accountPage.elements.registerSubmit} [type="submit"]`).click();

            // Checkout
            cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
            cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
            cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
            cy.get('.confirm-address').contains('John Doe');
            cy.get('.confirm-address .confirm-billing-address .card-actions .btn').click();
            cy.get('.address-editor-modal .address-editor-edit').click();
            cy.get('#address-create-edit input#vatIds').should('be.visible');
            cy.get('#address-create-edit input#vatIds').clear().type('22222');
            cy.get('#address-create-edit .address-form-submit').click();
        });
    });
});
