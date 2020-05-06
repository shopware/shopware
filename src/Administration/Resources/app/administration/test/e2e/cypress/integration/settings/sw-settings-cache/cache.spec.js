// / <reference types="Cypress" />

describe('Cache module', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/cache/index`);
            });
    });

    it('@settings: clear cache shortcut', () => {
        cy.get('.sw-loader').should('not.exist');
        cy.get('body').type('{alt}c', { release: false });
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-button--primary').click();
        cy.awaitAndCheckNotification('Clearing caches.');
        cy.awaitAndCheckNotification('All caches cleared.');
    });

    it('@settings: clear cache', () => {
        cy.contains('Caches & Indexes');

        cy.get('.sw-card__content .sw-container:first .sw-button').click();
        cy.awaitAndCheckNotification('Clearing caches.');
        cy.awaitAndCheckNotification('All caches cleared.');
    });

    it('@settings: rebuild index', () => {
        cy.contains('Caches & Indexes');

        cy.get('.sw-card__content .sw-container:last .sw-button').click();
        cy.awaitAndCheckNotification('Building indexes.');
    });
});
