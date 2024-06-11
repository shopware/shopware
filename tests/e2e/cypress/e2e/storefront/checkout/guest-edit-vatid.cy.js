import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';
let product = {};

/**
 * @package checkout
 */
describe('Checkout as Guest', () => {
    beforeEach(() => {
        return cy.createProductFixture().then(() => {
            return cy.createDefaultFixture('category');
        }).then(() => {
            return cy.fixture('product');
        }).then((result) => {
            product = result;
            cy.visit('/account/login');
        });
    });

    it('@base @checkout: Edit VAT id when confirm order', { tags: ['pa-checkout'] }, () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: 'api/_action/system-config/batch',
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
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
        cy.get('.confirm-address .confirm-billing-address .card-actions .btn').click();
        cy.get('.address-editor-modal .address-editor-edit').click();
        cy.get('#billing-edit-address-create-edit input#vatIds').should('be.visible');
        cy.get('#billing-edit-address-create-edit input#vatIds').clear().type('22222');
        cy.get('#billing-edit-address-create-edit .address-form-submit').click();
    });
});
