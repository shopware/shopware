import CheckoutPageObject from '../../support/pages/checkout.page-object';
import AccountPageObject from '../../support/pages/account.page-object';

let product = {};

describe('Checkout: Visual tests', () => {
    beforeEach(() => {
        cy.visit('/');
    });

    it('@visual: check appearance of basic checkout workflow', () => {
        if(!Cypress.env('testDataUsage')) {
            return;
        }

        const page = new CheckoutPageObject();
        const accountPage = new AccountPageObject();

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`Checkout - Search product`,
            '.header-search-input',
            { widths: [375, 1920] }
        );

        // Product detail
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Adidas');
        cy.get('.search-suggest-product-name').contains('Adidas R.Y.V. Hoodie');
        cy.contains('.search-suggest-product-name','Adidas R.Y.V. Hoodie').click();
        cy.get('.product-detail-name').contains('Adidas R.Y.V. Hoodie');
        // Take snapshot for visual testing
        cy.takeSnapshot(`Checkout - See product`,
            '.product-detail-buy',
            { widths: [375, 1920] }
        );

        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas').should('be.visible');
        cy.get('.cart-item-price').contains('64');

        const continueShopping = Cypress.env('locale') === 'en-GB' ?
            'Continue shopping' : 'Weiter einkaufen';
        cy.contains(continueShopping).should('be.visible');
        cy.contains(continueShopping).click();
        cy.get('.header-cart-total').contains('64');
        cy.get('.header-cart-total').click();
        cy.get('.offcanvas').should('be.visible');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`Checkout - Offcanvas`,
            `${page.elements.offCanvasCart}.is-open`,
            { widths: [375, 1920] }
        );
        cy.get('.cart-item-label').contains('Adidas R.Y.V. Hoodie');

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();
        cy.get('.login-form').should('be.visible');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`Checkout - Login`, accountPage.elements.loginCard, {widths: [375, 1920]});

        cy.get('#loginMail').type('kathie.jaeger@test.com');
        cy.get('#loginPassword').type('shopware');
        cy.get('.login-submit > .btn[type="submit"]').click();

        // Confirm
        const terms = Cypress.env('locale') === 'en-GB' ?
            'Terms and conditions and cancellation policy' : 'AGB und Widerrufsbelehrung';
        cy.get('.confirm-tos .card-title').contains(terms);
        cy.get('.confirm-address').contains('Kathie Jäger');
        cy.get('.cart-item-label').contains('Adidas R.Y.V. Hoodie');
        cy.get('.cart-item-total-price').contains('64');
        cy.get('.col-5.checkout-aside-summary-total').contains('64');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('Checkout - Confirm', '.confirm-tos', { widths: [375, 1920] });

        // Set payment and shipping
        cy.contains('Zahlungsart auswählen').click();
        cy.get('#confirmPaymentModal').should('be.visible');
        cy.contains('Vorkasse').click();
        cy.get('#confirmPaymentForm .btn-primary').click();
        cy.get('#confirmPaymentModal').should('not.visible');

        cy.contains('Versandart auswählen').click();
        cy.get('#confirmShippingModal').should('be.visible');
        cy.contains('Standard').click();
        cy.get('#confirmShippingForm .btn-primary').click();
        cy.get('#confirmShippingModal').should('not.visible');

        // Finish checkout
        cy.get('.confirm-tos .card-title').contains('AGB und Widerrufsbelehrung');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains(' Vielen Dank für Ihre Bestellung bei Footwear!');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot('Checkout - Finish', '.finish-header', {widths: [375, 1920]});

        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.login();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.changeElementStyling('.sw-data-grid__cell--orderDateTime', 'color: #fff');
        cy.takeSnapshot(`Order listing`, '.sw-order-list');

        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-order-detail').should('be.visible');

        // Take snapshot for visual testing
        cy.changeElementStyling('.sw-order-user-card__metadata-item', 'color: #fff');
        cy.changeElementStyling(
            '.sw-order-state-history-card__payment-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.changeElementStyling(
            '.sw-order-state-history-card__delivery-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.changeElementStyling(
            '.sw-order-state-history-card__order-state .sw-order-state-card__date',
            'color: #fff'
        );
        cy.changeElementStyling(
            '.sw-card-section--secondary > .sw-container > :nth-child(2) > :nth-child(4)',
            'color: rgb(240, 242, 245);'
        );
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('Order detail', '.sw-order-detail');

    });
});
