/// <reference types="Cypress" />

describe('Minimal auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */
    it('@update: de-DE and EUR', { tags: ['pa-system-settings'] }, () => {
        // Routes to wait for
        cy.intercept({ url: '*download-latest-update*', method: 'get' }).as('downloadLatestUpdate');
        cy.intercept({ url: '*unpack*', method: 'get' }).as('unpack');

        cy.clearCookies();
        cy.clearCookie('bearerAuth');
        cy.clearCookie('refreshBearerAuth');

        cy.visit('/admin');

        cy.get('#sw-field--username').type('admin');
        cy.get('#sw-field--password').type('shopware');

        cy.get('.sw-login__login-action').click();

        cy.get('.sw-version__info').contains('6.4.17.2', {timeout: 60000});

        let tag = Cypress.env('expectedVersion');
        let version = tag[0] === 'v' ? tag.slice(1) : tag;

        cy.get('.sw-alert__actions > :nth-child(1) > .sw-button__content').should('be.visible').click();

        cy.get('.smart-bar__header > h2').contains('(' + version).should('be.visible');

        // TODO: plugin step

        cy.get('.smart-bar__actions button.sw-button--primary')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-shopware-updates-check__start-update .sw-field--checkbox label')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-shopware-updates-check__start-update-actions > .sw-button--primary')
            .should('be.enabled')
            .click();

        cy.wait('@downloadLatestUpdate', { responseTimeout: 600000, timeout: 600000 })
            .its('response.statusCode').should('equal', 200);

        cy.wait('@unpack', { responseTimeout: 600000, timeout: 600000 })
            .its('response.statusCode').should('equal', 200);

        cy.get('.welcome-title').contains('Welcome');
        cy.get('.btn-primary').click();

        cy.get('.card__title').contains('Configuration');
        cy.get('.btn-primary').click();

        // Show basic info
        cy.get('.card__title').contains('Updating Shopware');

        cy.get('.btn-primary').click();

        // wait for /update/_finish ajax call to finish

        cy.intercept('/shopware-installer.phar.php/update/_finish').as('updateFinish');
        cy.wait('@updateFinish', {timeout: 120000});

        // Shows finish page
        cy.url().should('contain', '/finish');
        cy.get('.card__title', {timeout: 60000}).contains('Finish');

        cy.get('.btn-primary').click();

        cy.get('#sw-field--username').type('admin');
        cy.get('#sw-field--password').type('shopware');

        cy.get('.sw-login__login-action').click();

        cy.get('.sw-version__info').contains('6.6.');

        // visit updater and expect 404
        cy.visit('/shopware-installer.phar.php', {failOnStatusCode: false});
        cy.contains('Page not found');
    });
});
