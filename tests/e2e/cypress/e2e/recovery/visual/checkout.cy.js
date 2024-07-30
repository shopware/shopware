import CheckoutPageObject from '../../../support/pages/checkout.page-object';
import AccountPageObject from '../../../support/pages/account.page-object';

describe('Checkout: Visual tests', () => {
    beforeEach(() => {
        cy.visit('/');
    });

    it('@visual: check appearance of basic checkout workflow', { tags: ['pa-services-settings'] }, () => {
        if(!Cypress.env('testDataUsage')) {
            return;
        }

        const page = new CheckoutPageObject();
        const accountPage = new AccountPageObject();

        cy.intercept({
            url: '/widgets/checkout/info',
            method: 'get',
        }).as('cartInfo');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Checkout - Search product`,
            '.header-search-input',
            {widths: [375, 1920]},
        );

        // Product detail
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Adidas');
        cy.get('.search-suggest-product-name').contains('Adidas R.Y.V. Hoodie');
        cy.contains('.search-suggest-product-name', 'Adidas R.Y.V. Hoodie').click();
        cy.get('.product-detail-name').contains('Adidas R.Y.V. Hoodie');
        // Take snapshot for visual testing
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Checkout - See product`,
            '.product-detail-buy',
            {widths: [375, 1920]},
        );

        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas').should('be.visible');
        cy.wait('@cartInfo').its('response.statusCode').should('within', 200, 204);
        cy.get('.line-item-price').contains('64');

        const continueShopping = Cypress.env('locale') === 'en-GB' ?
            'Continue shopping' : 'Weiter einkaufen';
        cy.contains(continueShopping).should('be.visible');
        cy.contains(continueShopping).click();

        cy.get('.header-cart').click();

        cy.get('.offcanvas-cart').should('be.visible');
        cy.wait('@cartInfo').its('response.statusCode').should('within', 200, 204);
        cy.get('.header-cart-total').contains('64');
        cy.get('.summary-value').contains('64');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Checkout - Offcanvas`,
            page.elements.offCanvasCart,
            {widths: [375, 1920]},
        );
        cy.get('.line-item-label').contains('Adidas R.Y.V. Hoodie');

        // Checkout
        cy.get('.offcanvas-cart-actions .btn-primary').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();
        cy.get('.login-form').should('be.visible');

        const country = Cypress.env('locale') === 'en-GB' ? 'United Kingdom' : 'Deutschland';
        cy.get('select[name="billingAddress[countryId]"]').select(country);
        cy.get('.register-billing > .country-and-state-form-elements > .d-none').should('not.exist');
        cy.get('#billingAddressAddressCountryState').should('be.visible');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Checkout - Login`, accountPage.elements.loginCard, {widths: [375, 1920]});

        cy.get('#loginMail').type('kathie.jaeger@test.com');
        cy.get('#loginPassword').type('shopware');
        cy.get('.login-submit > .btn[type="submit"]').click();

        // Confirm
        const terms = Cypress.env('locale') === 'en-GB' ?
            'Terms and conditions and cancellation policy' : 'AGB und Widerrufsbelehrung';
        cy.get('.confirm-tos .card-title').contains(terms);
        cy.get('.confirm-address').contains('Kathie Jäger');
        cy.get('.line-item-label').contains('Adidas R.Y.V. Hoodie');
        cy.get('.line-item-total-price').contains('64');
        cy.get('.col-5.checkout-aside-summary-total').contains('64');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Checkout - Confirm`, '.confirm-tos', {widths: [375, 1920]});

        // Select invoice payment method and verify checked radio
        cy.get('.payment-method-radio').contains('Rechnung').should('be.visible');
        cy.get('.payment-method-radio').contains('Rechnung').click();
        cy.get('.payment-method-radio').contains('Rechnung').get('input[type="radio"]').should('be.checked');

        // Select standard shipping and verify checked radio
        cy.get('.shipping-method-radio').contains('Standard').should('be.visible');
        cy.get('.shipping-method-radio').contains('Standard').click();
        cy.get('.shipping-method-radio').contains('Standard').get('input[type="radio"]').should('be.checked');

        // Finish checkout
        cy.get('.confirm-tos .card-title').contains('AGB und Widerrufsbelehrung');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains(' Vielen Dank für Ihre Bestellung bei Footwear!');

        // Take snapshot for visual testing on desktop
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Checkout - Finish`, '.finish-header', {widths: [375, 1920]});

        cy.login();
        cy.visit(`${Cypress.env('admin')}#/sw/order/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.prepareAdminForScreenshot();

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--orderDateTime', 'color: #fff');

        // Take snapshot for visual testing
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Order listing`, '.sw-order-list');

        cy.prepareAdminForScreenshot();
        cy.clickContextMenuItem(
            '.sw-order-list__order-view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
            '', true,
        );
        cy.get('.sw-order-detail').should('be.visible');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-order-user-card__metadata-item', '01 Jan 2018, 00:00');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-order-state-history-card__payment-state .sw-order-state-card__date', '01 Jan 2018, 00:00');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-order-state-history-card__delivery-state .sw-order-state-card__date', '01 Jan 2018, 00:00');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.sw-order-state-history-card__order-state .sw-order-state-card__date', '01 Jan 2018, 00:00');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('div.sw-card.sw-card--grid.has--header.has--title.sw-order-user-card > div.sw-card__content > div > div.sw-card-section.sw-card-section--secondary.sw-card-section--slim > div > dl:nth-child(2) > dd:nth-child(4)', '01 Jan 2018, 00:00');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('div.sw-card.has--header.has--title.sw-order-delivery-metadata > div.sw-card__content > div > dl:nth-child(1) > dd:nth-child(4)', '01 Jan 2018, 00:00');

        cy.prepareAdminForScreenshot();

        // Take snapshot for visual testing
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Order detail`, '.sw-order-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
