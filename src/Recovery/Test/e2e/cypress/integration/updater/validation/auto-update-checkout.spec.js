// / <reference types="Cypress" />

describe('Validate checkout after auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */
    before(() => {
        cy.visit('/admin');

        cy.get('.sw-login__content').should('be.visible');
        cy.get('#sw-field--username').clear().type(Cypress.env('user'));
        cy.get('#sw-field--password').clear().type(Cypress.env('pass'));
        cy.get('.sw-button__content').click();

        let tag = Cypress.env('expectedVersion');
        let version = tag[0] === 'v' ? tag.slice(1) : tag;

        cy.get('.sw-alert__actions > :nth-child(1) > .sw-button__content').should('be.visible').click();

        cy.get('.smart-bar__header > h2').contains('(' + version + ')').should('be.visible');

        // TODO: plugin step

        cy.get('.sw-button__content')
            .contains('Update starten')
            .should('be.visible')
            .click();

        cy.get('.sw-field--checkbox label')
            .contains('Ja, ich habe ein Backup erstellt.')
            .should('be.visible')
            .click();

        cy.server();
        cy.route({ url: '/api/v1/_action/update/download-latest-update*', method: 'get' }).as('downloadLatestUpdate');
        cy.route({ url: '/api/v1/_action/update/deactivate-plugins*', method: 'get' }).as('deactivatePlugins');
        cy.route({ url: '/api/v1/_action/update/unpack*', method: 'get' }).as('unpack');
        cy.route({url: '*applyMigrations*', method: 'get'}).as('applyMigrations');

        cy.get('.sw-settings-shopware-updates-check__start-update-actions > .sw-button--primary')
            .should('be.enabled')
            .click();

        cy.wait('@downloadLatestUpdate', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });
        cy.wait('@deactivatePlugins', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });
        cy.wait('@unpack', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });

        cy.get('section.content--main', { timeout: 120000 }).should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Datenbank-Migration');
        cy.get('.content--main h2').contains('Datenbank-Update durchführen');

        cy.wait('@applyMigrations', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });

        cy.get('[name="cleanupForm"]', { timeout: 120000 }).should('be.visible');
        cy.get('.is--active > .navigation--link', { timeout: 1000 }).contains('Aufräumen');
        cy.get('.content--main h2').contains('Aufräumen');
        cy.get('.btn.btn-primary').contains('Weiter').click();

        cy.get('.alert-hero-title').should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Fertig');
        cy.get('.alert-hero-title').contains('Das Update war erfolgreich!');
        cy.get('.btn.btn-primary').contains('Update abschließen').click();

        cy.getCookie('bearerAuth')
            .then((val) => {
                // we need to login, if the new auth cookie does not exist - e.g. update from 6.1.x -> 6.2.x
                if (!val) {
                    cy.get('.sw-login__content').should('be.visible');
                    cy.get('#sw-field--username').clear().type(Cypress.env('user'));
                    cy.get('#sw-field--password').clear().type(Cypress.env('pass'));
                    cy.get('.sw-button__content').click();
                }
            });

        cy.get('.sw-version__info').contains(tag).should('be.visible');
    });

    it('@update: Check checkout process after update', () => {
        cy.visit('/');

        // Product detail
        cy.get('.header-search-input')
            .should('be.visible')
            .type('Adidas');
        cy.get('.search-suggest-product-name').contains('Adidas R.Y.V. Hoodie');
        cy.contains('.search-suggest-product-name','Adidas R.Y.V. Hoodie').click();
        cy.get('.product-detail-name').contains('Adidas R.Y.V. Hoodie');
        cy.get('.product-detail-buy .btn-buy').click();

        // Off canvas
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-label').contains('Adidas R.Y.V. Hoodie');

        // Checkout
        cy.get('.begin-checkout-btn').click();

        // Login
        cy.get('.checkout-main').should('be.visible');
        cy.get('.login-collapse-toggle').click();
        cy.get('.login-form').should('be.visible');
        cy.get('#loginMail').type('kathie.jaeger@test.com');
        cy.get('#loginPassword').type('shopware');
        cy.get('.login-submit > .btn[type="submit"]').click();

        // Confirm
        cy.get('.confirm-address').contains('Kathie Jäger');
        cy.get('.cart-item-label').contains('Adidas R.Y.V. Hoodie');
        cy.get('.cart-item-total-price').contains('64');
        cy.get('.col-5.checkout-aside-summary-total').contains('64');

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
    });
});
