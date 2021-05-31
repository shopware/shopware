/// <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */
    it('@update: de-DE and EUR', () => {
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
        cy.route({ url: '*download-latest-update*', method: 'get' }).as('downloadLatestUpdate');
        cy.route({ url: '*deactivate-plugins*', method: 'get' }).as('deactivatePlugins');
        cy.route({ url: '*unpack*', method: 'get' }).as('unpack');
        cy.route({ url: '*applyMigrations*', method: 'get' }).as('applyMigrations');

        cy.get('.sw-settings-shopware-updates-check__start-update-actions > .sw-button--primary')
            .should('be.enabled')
            .click();

        cy.wait('@downloadLatestUpdate', { responseTimeout: 600000, timeout: 600000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });
        cy.wait('@deactivatePlugins', { responseTimeout: 600000, timeout: 600000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });
        cy.wait('@unpack', { responseTimeout: 600000, timeout: 600000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });

        cy.get('section.content--main', { timeout: 120000 }).should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Datenbank-Migration');
        cy.get('.content--main h2').contains('Datenbank-Update durchführen');

        // Take snapshot for visual testing
        cy.get('.navigation--entry.is--complete').contains('Systemvoraussetzungen');
        cy.takeSnapshot('Migration');

        cy.wait('@applyMigrations', { responseTimeout: 300000, timeout: 310000 })
            .then((xhr) => {
                expect(xhr).to.have.property('status', 200);
            });

        cy.get('[name="cleanupForm"]', { timeout: 120000 }).should('be.visible');
        cy.get('.is--active > .navigation--link', { timeout: 1000 }).contains('Aufräumen');
        cy.get('.navigation--entry.is--complete').contains('Datenbank-Migration');
        cy.get('.content--main h2').contains('Aufräumen');

        // Change display of the element to ensure consistent snapshots
         cy.changeElementStyling(
             '[name="cleanupForm"] table',
             'display: none'
         );

        // Take snapshot for visual testing
        cy.takeSnapshot('Cleanup');
        cy.get('.btn.btn-primary').contains('Weiter').click();

        cy.get('.alert-hero-title').should('be.visible');
        cy.get('.navigation--list .is--active .navigation--link').contains('Fertig');
        cy.get('.alert-hero-title').contains('Das Update war erfolgreich!');

        // Take snapshot for visual testing
        cy.get('.navigation--entry.is--complete').contains('Aufräumen');
        cy.takeSnapshot('Finish');

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
            })

        cy.get('.sw-version__info').should('be.visible');
    });
});
