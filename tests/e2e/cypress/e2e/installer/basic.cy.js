/// <reference types="Cypress" />

describe('Minimal install', () => {
    before(() => {
        Cypress.env('SKIP_INIT', 'true');
        Cypress.env('SKIP_AUTH', 'true');
    });

    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must not exists
     * - install.lock must not exist
     */
    it('@install: en-GB and GBP', { tags: ['pa-services-settings'] }, () => {
        cy.visit('/installer', {
            headers: {
                'Accept-Language': Cypress.env('acceptLanguage'),
            },
        });

        cy.get('.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Start');
        cy.get('option:checked').contains('English');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Start`, '.content--main');

        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: requirements
        cy.get('.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('System requirements');
        cy.get('.requirement-group').should('have.class', 'success');
        cy.get('#pathChecks').should('not.have.attr', 'open');
        cy.get('#systemChecks').should('not.have.attr', 'open');

        // Take snapshot for visual testing
        cy.takeSnapshot( `Requirement`, '.content--main');

        cy.get('#requirement-group-path').click();
        cy.get('#pathChecks').should('have.attr', 'open');
        cy.get('#systemChecks').should('not.have.attr', 'open');
        cy.get('#pathChecks td > span.status-indicator').should('have.class', 'success');

        cy.get('#requirement-group-path').click();
        cy.get('#pathChecks').should('not.have.attr', 'open');
        cy.get('#systemChecks').should('not.have.attr', 'open');

        cy.get('#requirement-group-system').click();
        cy.get('#pathChecks').should('not.have.attr', 'open');
        cy.get('#systemChecks').should('have.attr', 'open');
        cy.get('#systemChecks td > span.status-indicator').should('have.class', 'success');

        cy.get('#requirement-group-system').click();
        cy.get('#pathChecks').should('not.have.attr', 'open');
        cy.get('#systemChecks').should('not.have.attr', 'open');

        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: GTC
        cy.get('.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('GTC');
        cy.url().should('contains', '/license');
        cy.get('.btn.btn-primary').contains('Next').click();
        cy.url().should('contains', '/license');

        // Take snapshot for visual testing
        cy.takeSnapshot(`GTC`, '.content--main');

        cy.get('.custom-checkbox').click();
        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: database config
        cy.url().should('contains', '/database-configuration');
        cy.get('.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Database configuration');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Database configuration`, '.content--main');

        cy.get('#hostname').clear().type(Cypress.env('dbHost'));
        cy.get('#username').clear().type(Cypress.env('dbUser'));
        cy.get('#password').clear().type(Cypress.env('dbPassword'));

        cy.get('.create_database').click();

        cy.get('#databaseName_new').clear().type(Cypress.env('dbName'));
        cy.get('.btn.btn-primary').contains('Start installation').click();

        // @install: installation
        cy.get('.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Installation');
        cy.get('.database-import-finish', { timeout: 400000 }).should('be.visible');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Database migration finished`, '.content--main');

        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: configuration
        cy.get('.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Configuration');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Configuration`, '.content--main');

        cy.get('#config_shopName').clear().type('E2E install test');
        cy.get('#config_mail').clear().type('e2e@example.com');
        cy.get('#config_shop_language option:checked').contains('English');
        cy.get('#config_shop_currency option:checked').contains('Euro');

        cy.get('#config_shop_currency').select('GBP');

        // check if the shop currency is disabled in the additional currencies
        cy.get('input#gbp').should('be.disabled');
        cy.get('input#gbp').should('be.checked');

        // add additional currencies
        cy.get('input#sek').check({ force: true });
        cy.get('input#eur').check({ force: true });

        cy.get('#config_admin_email').clear().type('e2e@example.com');

        cy.get('#config_admin_firstName').clear().type('e2e');
        cy.get('#config_admin_lastName').clear().type('shopware');
        cy.get('#config_admin_username').clear().type('admin');
        cy.get('#config_admin_password').clear().type('shopware');

        cy.get('.alert.alert-error').should('not.exist');

        cy.get('.btn.btn-primary').contains('Next').click();

        // See if redirect to Admin was successful
        cy.get('.sw-desktop').should('be.visible');

        // @frw in Administration: welcome
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-first-run-wizard__welcome-image').should('be.visible');
        cy.get('.sw-admin-menu__sales-channel-item').should('be.visible');
        cy.get('.sw-plugin-card__manufacturer').should('be.visible');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('FRW - Welcome', '.sw-modal.sw-first-run-wizard-modal');

        cy.get('.sw-button span').contains('Next').click();

        // @frw: skip data-import
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Data import');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('FRW - Data', '.sw-modal.sw-first-run-wizard-modal');
        cy.get('.sw-button span').contains('Next').click();

        // @frw: define no default sales channel for product creation
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Default values');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('FRW - Default values', '.sw-modal.sw-first-run-wizard-modal');
        cy.get('.sw-button span').contains('Next').click();
        cy.get('.sw-loader__element').should('not.exist');

        // @frw: skip mail configuration
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-first-run-wizard-mailer-selection__headline').contains('Establishing email communication');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('FRW - Mail', '.sw-modal.sw-first-run-wizard-modal');
        cy.get('.sw-button span').contains('Configure later').click();

        // @frw: skip paypal
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('PayPal setup');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('FRW - PayPal', '.sw-modal.sw-first-run-wizard-modal');
        cy.get('.sw-button span').contains('Skip').click();

        // @frw: plugins
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Extensions');
        cy.get('.sw-button span').contains('Next').click();

        // @frw: skip account login
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Shopware Account');
        cy.get('.sw-button span').contains('Skip').click();

        // @frw: skip store page
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-first-run-wizard-store').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Shopware Store');
        cy.get('.sw-button').should('not.be.disabled');
        cy.get('.sw-button span').contains('Skip').click();

        // @frw: finish
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-first-run-wizard-finish').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--success span').contains('Shopware Store');

        cy.intercept({
            url: '/api/_action/store/frw/finish',
            method: 'post',
        }).as('finishCall');

        cy.get('.sw-button span').contains('Finish').click();

        cy.wait('@finishCall').its('response.statusCode').should('equal', 200);


        cy.location().should((loc) => {
            expect(loc.pathname).to.eq(`${Cypress.env('admin')}`);
        });

        // Verify dashboard module
        cy.get('.sw-dashboard-index__content').should('be.visible');
    });
});
