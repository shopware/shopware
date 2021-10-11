/// <reference types="Cypress" />

describe('Minimal install', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must not exists
     * - install.lock must not exist
     */
    it('@install: nl-NL and Euro', () => {
        cy.visit('/recovery/install/index.php', {
            headers: {
                'Accept-Language': Cypress.env('acceptLanguage')
            }
        });

        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Start');
        cy.get('option:checked').contains('English');
        cy.get('#language').select('Dutch');
        cy.contains('Nederlands');
        cy.get('.welcome-title').contains('Welkom bij Shopware 6');

        cy.get('.btn.btn-primary').contains('Verder').click();

        // @install: requirements
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Systeemvereisten');
        cy.get('.requirement-group').should('have.class', 'success');
        cy.get('#permissions').should('be.hidden');
        cy.get('#systemchecks').should('be.hidden');

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

        cy.get('.btn.btn-primary').contains('Verder').click();

        // @install: GTC
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Algemene voorwaarden');
        cy.url().should('contains', '/license');
        cy.get('.btn.btn-primary').contains('Verder').click();
        cy.url().should('contains', '/license');

        cy.get('.custom-checkbox').click();
        cy.get('.btn.btn-primary').contains('Verder').click();

        // @install: database config
        cy.url().should('contains', '/database-configuration');
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Configuratie databank');

        cy.get('#c_database_host').clear().type(Cypress.env('dbHost'));
        cy.get('#c_database_user').clear().type(Cypress.env('dbUser'));
        cy.get('#c_database_password').clear().type(Cypress.env('dbPassword'));

        cy.get('.c_create_database').click();

        cy.get('#c_database_schema_new').clear().type(Cypress.env('dbName'));
        cy.get('.btn.btn-primary').contains('Installatie starten').click();

        // @install: installation
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Installatie');
        cy.get('.database-import-finish', { timeout: 300000 }).should('be.visible');

        cy.get('.btn.btn-primary').contains('Verder').click();

        // @install: configuration
        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .navigation--entry span').contains('Configuratie');

        cy.get('#c_config_shopName').clear().type('E2E install test');
        cy.get('#c_config_mail').clear().type('e2e@example.com');
        cy.get('#c_config_shop_language').select('Nederlands');
        cy.get('#c_config_shop_language').contains('Nederlands');
        cy.get('.footer-main > .is--active').contains('NL');

        cy.contains('Euro');

        // check if the shop currency is disabled in the additional currencies
        cy.get('input#eur').should('be.disabled');
        cy.get('input#eur').should('be.checked');


        cy.get('input#usd').check({ force: true });
        cy.get('input#usd').should('be.checked');

        cy.get('#c_config_admin_email').clear().type('e2e@example.com');

        cy.get('#c_config_admin_firstName').clear().type('e2e');
        cy.get('#c_config_admin_lastName').clear().type('shopware');
        cy.get('#c_config_admin_username').clear().type('admin');
        cy.get('#c_config_admin_password').clear().type('shopware');

        cy.get('.alert.alert-error').should('not.exist');

        cy.get('.btn.btn-primary').contains('Verder').click();

        // See if return to Admin was successful
        cy.get('.sw-desktop').should('be.visible');

        // @frw in Administration: welcome
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        cy.get('.sw-first-run-wizard__welcome-image').should('be.visible');

        cy.get('.sw-button span').contains('Next').click();

        // @frw: skip data-import
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Data import');

        cy.get('.sw-button span').contains('Next').click();

        // @frw: define no default sales channel for product creation
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('Default values');

        cy.get('.sw-button span').contains('Next').click();
        cy.get('.sw-loader__element').should('not.exist');

        // @frw: skip mail configuration
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-first-run-wizard-mailer-selection__headline').contains('Establishing email communication');

        cy.get('.sw-button span').contains('Configure later').click();

        // @frw: skip paypal
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-step-display .sw-step-item.sw-step-item--active span').contains('PayPal setup');

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

        cy.intercept({
            url: '/api/_action/store/frw/finish',
            method: 'post'
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
