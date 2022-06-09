/// <reference types="Cypress" />

describe('Minimal install', () => {
    before(() => {
       Cypress.env('SKIP_INIT', 'true');
    });

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
        cy.contains('.navigation--list .navigation--entry span', 'Start');
        cy.contains('option:checked', 'English');
        cy.get('#language').select('Dutch');
        cy.contains('Nederlands');
        cy.contains('.welcome-title', 'Welkom bij Shopware 6');

        cy.contains('.btn.btn-primary', 'Verder').click();

        // @install: requirements
        cy.get('section.content--main').should('be.visible');
        cy.contains('.navigation--list .navigation--entry span', 'Systeemvereisten');
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

        cy.contains('.btn.btn-primary', 'Verder').click();

        // @install: GTC
        cy.get('section.content--main').should('be.visible');
        cy.contains('.navigation--list .navigation--entry span', 'Algemene voorwaarden');
        cy.url().should('contains', '/license');
        cy.contains('.btn.btn-primary', 'Verder').click();
        cy.url().should('contains', '/license');

        cy.get('.custom-checkbox').click();
        cy.contains('.btn.btn-primary', 'Verder').click();

        // @install: database config
        cy.url().should('contains', '/database-configuration');
        cy.get('section.content--main').should('be.visible');
        cy.contains('.navigation--list .navigation--entry span', 'Configuratie databank');

        cy.get('#c_database_host').clear().type(Cypress.env('dbHost'));
        cy.get('#c_database_user').clear().type(Cypress.env('dbUser'));
        cy.get('#c_database_password').clear().type(Cypress.env('dbPassword'));

        cy.get('.c_create_database').click();

        cy.get('#c_database_schema_new').clear().type(Cypress.env('dbName'));
        cy.contains('.btn.btn-primary', 'Installatie starten').click();

        // @install: installation
        cy.get('section.content--main').should('be.visible');
        cy.contains('.navigation--list .navigation--entry span', 'Installatie');
        cy.get('.database-import-finish', { timeout: 300000 }).should('be.visible');

        cy.contains('.btn.btn-primary', 'Verder').click();

        // @install: configuration
        cy.get('section.content--main').should('be.visible');
        cy.contains('.navigation--list .navigation--entry span', 'Configuratie');

        cy.get('#c_config_shopName').clear().type('E2E install test');
        cy.get('#c_config_mail').clear().type('e2e@example.com');
        cy.get('#c_config_shop_language').select('Nederlands');
        cy.contains('#c_config_shop_language', 'Nederlands');
        cy.contains('.footer-main > .is--active', 'NL');

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

        cy.contains('.btn.btn-primary', 'Verder').click();

        // See if return to Admin was successful
        cy.get('.sw-desktop').should('be.visible');

        // @frw in Administration: welcome
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        cy.get('.sw-first-run-wizard__welcome-image').should('be.visible');

        cy.contains('.sw-button span', 'Next').click();

        // @frw: skip data-import
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');

        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'Data import');

        cy.contains('.sw-button span', 'Next').click();

        // @frw: define no default sales channel for product creation
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'Default values');

        cy.contains('.sw-button span', 'Next').click();
        cy.get('.sw-loader__element').should('not.exist');

        // @frw: skip mail configuration
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-first-run-wizard-mailer-selection__headline', 'Establishing email communication');

        // @frw: SwagPayPal install
        cy.intercept('POST', '**/api/_action/extension/install/plugin/SwagPayPal').as('installSwagPayPal');
        cy.contains('.sw-button span', 'Configure later').click();

        // @frw: skip paypal
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'PayPal setup');

        // the install can run into a race conditioning in the cache clear if it runs in parallel with the SwagMarkets install
        cy.wait('@installSwagPayPal').its('response.statusCode').should('equal', 204);

        cy.contains('.sw-button span', 'Skip').click();

        // @frw: Shopware Markets
        cy.intercept('PUT', '**/api/_action/extension/activate/plugin/SwagMarkets').as('activateSwagMarkets');

        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'Shopware Markets');

        cy.wait('@activateSwagMarkets').its('response.statusCode').should('equal', 204);
        cy.contains('.sw-button span', 'Next').click();

        // @frw: plugins
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'Extensions');
        cy.contains('.sw-button span', 'Next').click();

        // @frw: skip account login
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'Shopware Account');
        cy.contains('.sw-button span', 'Skip').click();

        // @frw: skip store page
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-first-run-wizard-store').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--active span', 'Shopware Store');
        cy.get('.sw-button').should('not.be.disabled');
        cy.contains('.sw-button span', 'Skip').click();

        // @frw: finish
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
        cy.get('.sw-step-display').should('be.visible');
        cy.get('.sw-first-run-wizard-finish').should('be.visible');
        cy.contains('.sw-step-display .sw-step-item.sw-step-item--success span', 'Shopware Store');

        cy.intercept({
            url: '/api/_action/store/frw/finish',
            method: 'post'
        }).as('finishCall');

        cy.contains('.sw-button span', 'Finish').click();

        cy.wait('@finishCall').its('response.statusCode').should('equal', 200);


        cy.location().should((loc) => {
            expect(loc.pathname).to.eq(`${Cypress.env('admin')}`);
        });

        // Verify dashboard module
        cy.get('.sw-dashboard-index__content').should('be.visible');
    });
});
