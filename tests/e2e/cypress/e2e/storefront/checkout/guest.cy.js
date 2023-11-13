import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';
let product = {};

/**
 * @package checkout
 */
describe(`Checkout as Guest`, () => {
    beforeEach(() => {
        cy.createDefaultFixture('category')
            .then(() => {
                return cy.createProductFixture();
            }, { timeout: 30000 }).then(() => {
                return cy.fixture('product');
            }).then((result) => {
                product = result;
                cy.visit('/account/login');
            });
    });

    it('@base @checkout @package: Run checkout', { tags: ['pa-checkout'] }, () => {
        const page = new CheckoutPageObject();
        const accountPage = new AccountPageObject();

        cy.window().then(() => {
            // Product detail
            cy.get('.header-search-input').should('be.visible');
            cy.get('.header-search-input').type(product.name);
            cy.get('.search-suggest-product-name').contains(product.name);
            cy.get('.search-suggest-product-price').contains(product.price[0].gross);
            cy.get('.search-suggest-product-name').click();
            cy.get('.product-detail-buy .btn-buy').click();

            // Off canvas
            cy.get(page.elements.offCanvasCart).should('be.visible');
            cy.get('.line-item-label').contains(product.name);

            // Checkout
            cy.get('.offcanvas-cart-actions .btn-primary').click();

            cy.get(accountPage.elements.registerCard).should('be.visible');

            cy.get('select[name="salutationId"]').select('Mr.');
            cy.get('input[name="firstName"]').type('John');
            cy.get('input[name="lastName"]').type('Doe');

            cy.get(`${accountPage.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');

            cy.get('input[name="billingAddress[street]"]').type('123 Main St');
            cy.get('input[name="billingAddress[zipcode]"]').type('9876');
            cy.get('input[name="billingAddress[city]"]').type('Anytown');

            cy.get('select[name="billingAddress[countryId]"]').select('United States of America');
            cy.get('select[name="billingAddress[countryStateId]"]').should('be.visible');
            cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');

            cy.get(`${accountPage.elements.registerSubmit} [type="submit"]`).click();

            // Checkout
            cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
            cy.get('.checkout-confirm-tos-label').scrollIntoView();
            cy.get('.checkout-confirm-tos-label').click(1, 1);
            cy.get('.confirm-address').contains('John Doe');
            cy.get('.line-item-details-container .line-item-label').contains(product.name);
            cy.get('.line-item-total-price').contains(product.price[0].gross);

            // Finish checkout
            cy.get('#confirmFormSubmit').scrollIntoView();
            cy.get('#confirmFormSubmit').click();
            cy.get('.finish-header').contains('Thank you for your order with Demostore!');
            cy.get('.checkout-aside-summary-total').contains(product.price[0].gross);
            cy.get('.col-5.checkout-aside-summary-value').contains(product.price[0].gross);

            // Logout
            cy.get('[title="Back to shop"]').click();
            cy.get('button#accountWidget').click();
            cy.contains('[aria-labelledby="accountWidget"]', 'Close guest session').should('be.visible');
        });
    });

    it('@base @checkout @package: Run checkout with account type', { tags: ['pa-checkout'] }, () => {
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
                        'core.cart.logoutGuestAfterCheckout' : true,
                    },
                },
            };

            return cy.request(requestConfig);
        });

        cy.visit('/account/login');

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
        cy.get(page.elements.offCanvasCart).should('be.visible');
        cy.get('.line-item-label').contains(product.name);

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
        cy.get(`.register-shipping ${billingAddressCompanySelector}`).should('not.exist');
        cy.get(`.register-shipping ${billingAddressDepartmentSelector}`).should('not.exist');
        cy.get(`.register-shipping ${vatIdsSelector}`).should('not.exist');

        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();
        // check Company, Department, VatId fields not exists in register shipping address form
        cy.get(`.register-shipping ${shippingAddressCompanySelector}`).should('not.be.visible').should('not.have.attr', 'required');
        cy.get(`.register-shipping ${shippingAddressDepartmentSelector}`).should('not.be.visible');
        cy.get(`.register-shipping ${vatIdsSelector}`).should('not.exist');

        cy.get(accountTypeSelector).typeAndSelect('Private');
        cy.get(billingAddressCompanySelector).should('not.be.visible');
        cy.get(billingAddressDepartmentSelector).should('not.be.visible');
        cy.get(vatIdsSelector).should('not.be.visible');

        cy.get(accountTypeSelector).typeAndSelect('Commercial');
        // check Company, Department, VatId fields not exists in register address form
        cy.get(`.register-address ${billingAddressCompanySelector}`).should('not.exist');
        cy.get(`.register-address ${billingAddressDepartmentSelector}`).should('not.exist');
        cy.get(`.register-address ${vatIdsSelector}`).should('not.exist');

        // check Company, Department, VatId fields not exists in register shipping address form
        cy.get(`.register-shipping ${shippingAddressCompanySelector}`).should('not.be.visible');
        cy.get(`.register-shipping ${shippingAddressDepartmentSelector}`).should('not.be.visible');
        cy.get(`.register-shipping ${vatIdsSelector}`).should('not.exist');

        cy.get(billingAddressCompanySelector).should('be.visible');
        cy.get(billingAddressCompanySelector).type('Company Testing');
        cy.get(billingAddressDepartmentSelector).should('be.visible');
        cy.get(billingAddressDepartmentSelector).type('Department Testing');
        cy.get(vatIdsSelector).type('vat-id-test');

        cy.get(shippingAccountTypeSelector).typeAndSelect('Commercial');
        cy.get(`.register-shipping ${shippingAddressCompanySelector}`).should('be.visible').should('have.attr', 'required');

        cy.get(`${accountPage.elements.registerForm} input[name="email"]`).type('john-doe-for-testing@example.com');

        cy.get('input[name="billingAddress[street]"]').type('123 Main St');
        cy.get('input[name="billingAddress[zipcode]"]').type('9876');
        cy.get('input[name="billingAddress[city]"]').type('Anytown');

        cy.get('select[name="billingAddress[countryId]"]').select('United States of America');
        cy.get('select[name="billingAddress[countryStateId]"]').should('be.visible');
        cy.get('select[name="billingAddress[countryStateId]"]').select('Ohio');
        cy.get('.register-different-shipping label[for="differentShippingAddress"]').click();

        cy.get(`${accountPage.elements.registerSubmit} [type="submit"]`).click();

        // Checkout
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
        cy.get('.checkout-confirm-tos-label').scrollIntoView();
        cy.get('.checkout-confirm-tos-label').click(1, 1);
        cy.get('.confirm-address').contains('John Doe');
        cy.get('.line-item-details-container .line-item-label').contains(product.name);
        cy.get('.line-item-total-price').contains(product.price[0].gross);

        // Finish checkout
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
        cy.get('.checkout-aside-summary-total').contains(product.price[0].gross);
        cy.get('.col-5.checkout-aside-summary-value').contains(product.price[0].gross);

        // Logout
        cy.get('[title="Back to shop"]').click();
        cy.get('button#accountWidget').click();
        cy.contains('[aria-labelledby="accountWidget"]', 'Your account').should('be.visible');
    });
});
