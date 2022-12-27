/// <reference types="Cypress" />

describe('Manual update', () => {
    it('@update: en-GB and EUR', { tags: ['pa-system-settings'] }, () => {
        cy.clearCookies();
        cy.clearCookie('bearerAuth')
        cy.clearCookie('refreshBearerAuth')

        // Check if the Installation is running
        cy.visit('/admin');

        cy.get('#sw-field--username').type('admin');
        cy.get('#sw-field--password').type('shopware');

        cy.get('.sw-login__login-action').click();

        cy.get('.sw-version__info').contains('6.4.17.2', {timeout: 60000});

        // Configure PHP
        cy.visit('/shopware-recovery.phar.php');
        cy.get('.card__title').contains('Configure PHP executable');
        cy.get('.btn-primary').click();

        // Show basic info
        cy.get('.card__title').contains('Updating Shopware to');

        cy.get('.btn-primary').click();

        // wait for /update/_finish ajax call to finish

        cy.intercept('/shopware-recovery.phar.php/update/_finish').as('updateFinish');
        cy.wait('@updateFinish', {timeout: 120000});

        // Shows finish page
        cy.get('.card__title', {timeout: 60000}).contains('Finish');

        cy.get('.btn-primary').click();

        cy.get('.sw-version__info').contains('6.5.');

        // visit updater and expect 404
        cy.visit('/shopware-recovery.phar.php', {failOnStatusCode: false});
        cy.contains('Page not found');
    });
});
