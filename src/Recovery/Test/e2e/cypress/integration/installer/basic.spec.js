/// <reference types="Cypress" />

describe('Minimal install', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must not exists
     * - install.lock must not exist
     */
    it('@install: en-GB and GBP', () => {
        cy.visit('/', {
            headers: {
                'Accept-Language': Cypress.env('acceptLanguage')
            }
        });

        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Start');
        cy.get('option:checked').contains('English');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Start`, 'section.content--main');

        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: requirements
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('System requirements');
        cy.get('.requirement-group').should('have.class', 'success');
        cy.get('#permissions').should('be.hidden');
        cy.get('#systemchecks').should('be.hidden');

        // Take snapshot for visual testing
        cy.takeSnapshot( `Requirement`, 'section.content--main');

        cy.get('.requirement-group[data-target="#permissions"]').click();
        cy.get('#permissions').should('be.visible');
        cy.get('#systemchecks').should('be.hidden');
        cy.get('#permissions td > span.status-indicator').should('have.class', 'success');

        cy.get('.requirement-group[data-target="#permissions"]').click();
        cy.get('#permissions').should('be.hidden');
        cy.get('#systemchecks').should('be.hidden');

        cy.get('.requirement-group[data-target="#systemchecks"]').click();
        cy.get('#permissions').should('be.hidden');
        cy.get('#systemchecks').should('be.visible');
        cy.get('#systemchecks td > span.status-indicator').should('have.class', 'success');

        cy.get('.requirement-group[data-target="#systemchecks"]').click();
        cy.get('#permissions').should('be.hidden');
        cy.get('#systemchecks').should('be.hidden');

        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: GTC
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('GTC');
        cy.url().should('contains', '/license');
        cy.get('.btn.btn-primary').contains('Next').click();
        cy.url().should('contains', '/license');

        // Take snapshot for visual testing
        cy.takeSnapshot(`GTC`, 'section.content--main');

        cy.get('.custom-checkbox').click();
        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: database config
        cy.url().should('contains', '/database-configuration');
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Database configuration');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Database configuration`, 'section.content--main');

        cy.get('#c_database_host').clear().type(Cypress.env('dbHost'));
        cy.get('#c_database_user').clear().type(Cypress.env('dbUser'));
        cy.get('#c_database_password').clear().type(Cypress.env('dbPassword'));

        cy.get('.c_create_database').click();

        cy.get('#c_database_schema_new').clear().type(Cypress.env('dbName'));
        cy.get('.btn.btn-primary').contains('Start installation').click();

        // @install: installation
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Installation');
        cy.get('.database-import-finish', { timeout: 180000 }).should('be.visible');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Database migration finished`, 'section.content--main');

        cy.get('.btn.btn-primary').contains('Next').click();

        // @install: configuration
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Configuration');

        // Take snapshot for visual testing
        cy.takeSnapshot(`Configuration`, 'section.content--main');

        cy.get('#c_config_shopName').clear().type('E2E install test');
        cy.get('#c_config_mail').clear().type('e2e@example.com');
        cy.get('#c_config_shop_language option:checked').contains('English');
        cy.get('#c_config_shop_currency option:checked').contains('Euro');

        cy.get('#c_config_shop_currency').select('GBP');

        // check if the shop currency is disabled in the additional currencies
        cy.get('input#gbp').should('be.disabled');
        cy.get('input#gbp').should('be.checked');

        // add additional currencies
        cy.get('input#sek').check({ force: true });
        cy.get('input#eur').check({ force: true });

        cy.get('#c_config_admin_email').clear().type('e2e@example.com');

        cy.get('#c_config_admin_firstName').clear().type('e2e');
        cy.get('#c_config_admin_lastName').clear().type('shopware');
        cy.get('#c_config_admin_username').clear().type('admin');
        cy.get('#c_config_admin_password').clear().type('shopware');

        cy.get('.alert.alert-error').should('not.exist');

        cy.get('.btn.btn-primary').contains('Next').click();

        // See if return to Admin was successful
        cy.get('.sw-desktop').should('be.visible');

        // @frw in Administration: welcome
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.get('.sw-first-run-wizard__welcome-image').should('be.visible');
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

        // @frw: skip mail configuration
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');


        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Mailer configuration');

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

        // @frw: Shopware Markets
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Shopware Markets');
        cy.get('.sw-button span').contains('Next').click();

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

        cy.server();
        cy.route({
            url: '/api/_action/store/frw/finish',
            method: 'post'
        }).as('finishCall');

        cy.get('.sw-button span').contains('Finish').click();

        cy.wait('@finishCall').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        }, { responseTimeout: 60000 });

        cy.location().should((loc) => {
            expect(loc.pathname).to.eq(`${Cypress.env('admin')}`);
        });

        // Verify dashboard module
        cy.get('.sw-dashboard-index__content').should('be.visible');
    });
});
