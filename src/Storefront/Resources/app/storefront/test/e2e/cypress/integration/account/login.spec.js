import AccountPageObject from '../../support/pages/account.page-object';

describe('Account: Login as customer', () => {

    beforeEach(() => {
        cy.createCustomerFixtureStorefront().then(() => {
            cy.createProductFixture();
        });
    });

    it('@login: Login with wrong credentials', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get(page.elements.loginCard).should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('Anything');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.alert-danger').should((element) => {
            expect(element).to.contain('Could not find an account that matches the given credentials.');
        });
    });

    it('@base @login: Login with valid credentials', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });
    });
    it('@login @package: Clear and delete cart after logout', () => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.invalidateSessionOnLogOut': true
                    }
                }
            };
            return cy.request(requestConfig);
        });

        const page = new AccountPageObject();
        cy.visit('/account/login');

        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        // Add product to cart
        cy.get('.header-search-input').should('be.visible').type('Product name');
        cy.contains('.search-suggest-product-name', 'Product name').click();
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Product name');

        // Go to cart
        cy.get('.offcanvas-cart-actions [href="/checkout/cart"]').click();
        cy.get('.cart-item-details-container [title]').contains('Product name');
        cy.get('.cart-item-total-price.col-12.col-md-2.col-sm-4').contains('€10.00*');
        cy.get('.header-cart-total').contains('€10.00*');

        // Logout
        cy.get('button#accountWidget').click();
        cy.get('.account-aside-footer').contains('Logout').click();

        // Login
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();
        cy.get('.header-cart-total').contains('€0.00*');
    });
});
