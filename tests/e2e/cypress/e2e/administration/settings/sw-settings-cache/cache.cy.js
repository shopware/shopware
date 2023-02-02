/// <reference types="Cypress" />

describe('Cache module', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/cache/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: clear cache shortcut', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        cy.get('.sw-loader').should('not.exist');
        cy.get('body').type('{alt}c', { release: false });
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-button--primary').click();
        cy.awaitAndCheckNotification('Clearing caches.');
        cy.awaitAndCheckNotification('All caches cleared.');
    });

    // NEXT-20024
    it('@base @settings: clear cache', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        cy.contains('Caches & indexes');

        cy.get('.sw-card__content .sw-container:first .sw-button').click();
        cy.awaitAndCheckNotification('Clearing caches.');
        cy.awaitAndCheckNotification('All caches cleared.');
    });

    it('@base @settings: rebuild index', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        cy.contains('Caches & indexes');

        cy.get('.sw-card__content .sw-container:last .sw-button').click();
        cy.awaitAndCheckNotification('Building indexes.');
    });

    it('@base @settings: rebuild index with skip options', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        cy.contains('Caches & indexes');

        cy.get('.sw-settings-cache__indexers-select').should('be.visible').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list').contains('category.indexer').click();
        cy.get('.sw-select-result-list__item-list').contains('category.indexer').parents('.sw-field--checkbox')
            .find('input[type="checkbox"]').should('be.checked');
        cy.get('.sw-select-result-list__item-list').contains('category.child-count').parents('.sw-field--checkbox')
            .find('input[type="checkbox"]').should('be.checked').should('be.disabled');
        cy.contains('.sw-label', 'category.indexer').should('be.visible');

        cy.get('.sw-card__content .sw-container:last .sw-button').click();
        cy.awaitAndCheckNotification('Building indexes.');
    });
});
