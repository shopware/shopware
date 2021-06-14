/// <reference types="Cypress" />

describe('Minimal install', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must not exists
     * - install.lock must not exist
     */
    it('@install: nl-NL and Euro', () => {
        cy.visit('/', {
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
        cy.get('.database-import-finish', { timeout: 180000 }).should('be.visible');

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

        cy.get('#c_config_admin_email').clear().type('e2e@example.com');

        cy.get('#c_config_admin_firstName').clear().type('e2e');
        cy.get('#c_config_admin_lastName').clear().type('shopware');
        cy.get('#c_config_admin_username').clear().type('admin');
        cy.get('#c_config_admin_password').clear().type('shopware');

        cy.get('.alert.alert-error').should('not.exist');

        cy.get('.btn.btn-primary').contains('Verder').click();

        // @frw in Administration: welcome
        cy.get('.sw-modal.sw-first-run-wizard-modal').should('be.visible');
    });
});
