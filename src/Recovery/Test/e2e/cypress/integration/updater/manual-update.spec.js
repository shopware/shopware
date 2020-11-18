/// <reference types="Cypress" />

describe('Manual update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update was unpacked
     */
    it('@update: en-GB and EUR', () => {
        cy.visit('/admin', {
            headers: {
                'Accept-Language': Cypress.env('acceptLanguage')
            },
            failOnStatusCode: false
        });

        cy.get('.maintenance-headline')
            .contains(/Unsere Website befindet sich gerade in der Wartung.|Our website is currently undergoing maintenance./)
            .should('be.visible');

        cy.visit('/', { failOnStatusCode: false });
        cy.get('.maintenance-headline')
            .contains(/Unsere Website befindet sich gerade in der Wartung.|Our website is currently undergoing maintenance./)
            .should('be.visible');

        cy.visit('/recovery/update/index.php/');


        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Start update').should('be.visible');
        cy.get('.content--main h2').contains('Start update').should('be.visible');
        cy.get('.btn.btn-primary').contains('Forward').click();


        cy.get('section.content--main').should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Database migration').should('be.visible');
        cy.get('.content--main h2').contains('Database migration').should('be.visible');

        cy.server();

        // match applyMigrations where offset == total
        cy.route({
            url: /.*applyMigrations\?offset=(\d+)&total=\1&modus=update$/,
            method: 'get'
        }).as('applyMigrations');

        // match applyMigrations migration where offset == total
        cy.route({
            url: /.*applyMigrations\?offset=(\d+)&total=\1&modus=update_destructive$/,
            method: 'get'
        }).as('applyDestructiveMigrations');

        // start migrations
        cy.get('.btn.btn-primary').contains('Start').click();

        cy.wait('@applyMigrations', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });
        cy.wait('@applyDestructiveMigrations', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });

        cy.get('[name="cleanupForm"]', { timeout: 120000 }).should('be.visible');
        cy.get('.is--active > .navigation--link', { timeout: 1000 }).contains('Cleanup').should('be.visible');
        cy.get('.content--main h2').contains('File cleanup').should('be.visible');
        cy.get('.btn.btn-primary').contains('Forward').should('be.visible').click();


        cy.get('.alert-hero-title').should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Done').should('be.visible');
        cy.get('.alert-hero-title').contains('The update was successful!').should('be.visible');

        cy.get('.btn.btn-primary').contains('Finish update').should('be.visible').click();

        cy.get('.sw-login__content').should('be.visible');
        cy.get('#sw-field--username').clear().type(Cypress.env('user'));
        cy.get('#sw-field--password').clear().type(Cypress.env('pass'));
        cy.get('.sw-button__content').click();

        cy.get('.sw-version__info').should('be.visible');
    });
});
