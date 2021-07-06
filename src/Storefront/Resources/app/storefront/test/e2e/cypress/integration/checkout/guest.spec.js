import CheckoutPageObject from "../../support/pages/checkout.page-object";
import AccountPageObject from "../../support/pages/account.page-object";
let product = {};

describe(`Checkout as Guest`, () => {
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

    it('@base @checkout: Run checkout', () => {
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

        cy.get('select[name="salutationId"]').select('Mr.');
        cy.get('input[name="firstName"]').type('John');
        cy.get('input[name="lastName"]').type('Doe');

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
        cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`).contains(product.name);
        cy.get(`${page.elements.cartItem}-total-price`).contains(product.price[0].gross);

        // Finish checkout
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
        cy.get('.checkout-aside-summary-total').contains(product.price[0].gross);
        cy.get('.col-5.checkout-aside-summary-value').contains('10.00');
    });

    it('@base @checkout: Run checkout with account type', () => {
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
            const shippingAccountTypeSelector = 'select[name="shippingAddress[accountType]"]';
            const billingAddressCompanySelector = 'input[name="billingAddress[company]"]';
            const billingAddressDepartmentSelector = 'input[name="billingAddress[department]"]';
            const shippingAddressCompanySelector = 'input[name="shippingAddress[company]"]';
            const shippingAddressDepartmentSelector = 'input[name="shippingAddress[department]"]';
            const vatIdsSelector = 'input#vatIds';
            cy.get(accountTypeSelector).should('be.visible');

            cy.get('select[name="salutationId"]').select('Mr.');
            cy.get('input[name="firstName"]').type('John');
            cy.get('input[name="lastName"]').type('Doe');

            // check Company, Department, VatId fields not exists in register address form
            cy.get(`.register-shipping ${billingAddressCompanySelector}`).should('not.be.visible');
            cy.get(`.register-shipping ${billingAddressDepartmentSelector}`).should('not.be.visible');
            cy.get(`.register-shipping ${vatIdsSelector}`).should('not.be.visible');

            cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();
            // check Company, Department, VatId fields not exists in register shipping address form
            cy.get(`.register-shipping ${shippingAddressCompanySelector}`).should('not.be.visible').should('not.have.attr', 'required');
            cy.get(`.register-shipping ${shippingAddressDepartmentSelector}`).should('not.be.visible');
            cy.get(`.register-shipping ${vatIdsSelector}`).should('not.be.visible');

            cy.get(accountTypeSelector).typeAndSelect('Private');
            cy.get(billingAddressCompanySelector).should('not.be.visible');
            cy.get(billingAddressDepartmentSelector).should('not.be.visible');
            cy.get(vatIdsSelector).should('not.be.visible');

            cy.get(accountTypeSelector).typeAndSelect('Commercial');
            // check Company, Department, VatId fields not exists in register address form
            cy.get(`.register-address ${billingAddressCompanySelector}`).should('not.be.visible');
            cy.get(`.register-address ${billingAddressDepartmentSelector}`).should('not.be.visible');
            cy.get(`.register-address ${vatIdsSelector}`).should('not.be.visible');

            // check Company, Department, VatId fields not exists in register shipping address form
            cy.get(`.register-shipping ${shippingAddressCompanySelector}`).should('not.be.visible');
            cy.get(`.register-shipping ${shippingAddressDepartmentSelector}`).should('not.be.visible');
            cy.get(`.register-shipping ${vatIdsSelector}`).should('not.be.visible');

            cy.get(billingAddressCompanySelector).should('be.visible');
            cy.get(billingAddressCompanySelector).type('Company Testing');
            cy.get(billingAddressDepartmentSelector).should('be.visible');
            cy.get(billingAddressDepartmentSelector).type('Department Testing');
            cy.get(vatIdsSelector).type('vat-id-test');

            cy.get(shippingAccountTypeSelector).typeAndSelect('Commercial');
            cy.get(`.register-shipping ${shippingAddressCompanySelector}`).should('be.visible').should('have.attr', 'required');

            cy.get(`${accountPage.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');
            cy.get('.register-guest-control.custom-checkbox label').scrollIntoView();
            cy.get('.register-guest-control.custom-checkbox label').click(1, 1);

            cy.get('input[name="billingAddress[street]"]').type('123 Main St');
            cy.get('input[name="billingAddress[zipcode]"]').type('9876');
            cy.get('input[name="billingAddress[city]"]').type('Anytown');

            cy.get('select[name="billingAddress[countryId]"]').select('USA');
            cy.get('select[name="billingAddress[countryStateId]"]').should('be.visible');
            cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');
            cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

            cy.get(`${accountPage.elements.registerSubmit} [type="submit"]`).click();

            // Checkout
            cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
            cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
            cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
            cy.get('.confirm-address').contains('John Doe');
            cy.get(`${page.elements.cartItem}-details-container ${page.elements.cartItem}-label`).contains(product.name);
            cy.get(`${page.elements.cartItem}-total-price`).contains(product.price[0].gross);

            // Finish checkout
            cy.get('#confirmFormSubmit').scrollIntoView();
            cy.get('#confirmFormSubmit').click();
            cy.get('.finish-header').contains('Thank you for your order with Demostore!');
            cy.get('.checkout-aside-summary-total').contains(product.price[0].gross);
            cy.get('.col-5.checkout-aside-summary-value').contains('10.00');
        });
    });
});
