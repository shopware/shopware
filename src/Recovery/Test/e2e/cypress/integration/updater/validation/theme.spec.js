// / <reference types="Cypress" />

describe('Validation of theme and cache after auto update', () => {
    /**
     * Test requirements:
     * - db `Cypress.env('dbName')` must exist
     * - install.lock must exist
     * - update-service-mock.js must be running
     */

    it('@update: Check caches after update', () => {
        cy.visit('/admin#/sw/settings/cache/index');
        cy.login();

        cy.contains('Caches leeren').click();
        cy.visit('/');
        cy.get('.btn-buy')
            .should('have.css', 'background-color', 'rgb(255, 72, 85)');
    });

    it('@update: Check theme compile', () => {
        cy.intercept({
            url: '/api/_action/theme/**/assign/**',
            method: 'POST'
        }).as('themeAssign');

        cy.visit('/admin');
        cy.login();

        cy.get('.sw-admin-menu__sales-channel-item--0').click();
        cy.get('a[title="Produkte"]').should('be.visible');
        cy.get('a[title="Theme"]').click();

        cy.contains('Theme-Zuweisung').should('be.visible');
        cy.contains('Theme ändern').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-theme-modal__content-item').contains('Shopware default theme').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Theme wechseln').should('be.visible');
        cy.contains('.sw-button--primary','Theme wechseln').click();

        // Ensure theme assignment request is successful
        cy.wait('@themeAssign').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Shopware default theme');

        cy.visit('/');
        cy.get('.btn-buy')
            .should('have.css', 'background-color', 'rgb(0, 132, 144)');

        cy.visit('/admin'); cy.get('.sw-admin-menu__sales-channel-item--0').click();
        cy.get('a[title="Produkte"]').should('be.visible');
        cy.get('a[title="Theme"]').click();

        cy.contains('Theme-Zuweisung').should('be.visible');
        cy.contains('Theme ändern').click();
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-theme-modal__content-item').contains('Footwear Theme').click();
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.get('.sw-loader').should('not.exist');

        cy.contains('Theme wechseln').should('be.visible');
        cy.contains('.sw-button--primary','Theme wechseln').click();

        // Ensure theme assignment request is successful
        cy.wait('@themeAssign').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.contains('Footwear Theme');

        cy.visit('/');
        cy.get('.btn-buy')
            .should('have.css', 'background-color', 'rgb(255, 72, 85)');
    });
});
