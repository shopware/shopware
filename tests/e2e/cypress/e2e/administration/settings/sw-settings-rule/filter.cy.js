// / <reference types="Cypress" />

describe('Rule: Testing filter and reset filter', () => {
    beforeEach(() => {
        cy.createDefaultFixture('rule');
    });

    it('@settings: check filter function and display listing correctly',  { tags: ['pa-business-ops'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/rule`,
            method: 'POST',
        }).as('filterRule');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('getUserConfig');

        cy.wait('@filterRule')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-sidebar-navigation-item[title="Filters"]').click();

        cy.get('.sw-filter-panel').should('be.visible');

        cy.get('#conditionGroups').type('General').type('{enter}');
        cy.wait('@filterRule').its('response.statusCode').should('equal', 200);
        cy.get('.sw-rule-list-grid').should('be.visible');
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.get('.sw-page__smart-bar-amount').contains('2');
        cy.get('.sw-sidebar-navigation-item.is--active').find('.notification--badge').should('have.text', '1');

        cy.get('#conditions').type('Day of the week').type('{enter}');
        cy.wait('@filterRule').its('response.statusCode').should('equal', 200);
        cy.get('.sw-rule-list-grid').should('be.visible');
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.get('.sw-page__smart-bar-amount').contains('1');
        cy.get('.sw-sidebar-navigation-item.is--active').find('.notification--badge').should('have.text', '2');

        cy.get('#conditions').find('.sw-base-filter__reset').should('exist');
        cy.get('#conditions').find('.sw-base-filter__reset').click();

        cy.wait('@filterRule').its('response.statusCode').should('equal', 200);
        cy.get('.sw-page__smart-bar-amount').contains('2');

        cy.get('#conditionGroups').find('.sw-base-filter__reset').should('exist');
        cy.get('#conditionGroups').find('.sw-base-filter__reset').click();

        cy.wait('@filterRule').its('response.statusCode').should('equal', 200);
        cy.get('.sw-rule-list-grid').should('be.visible');
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.contains('.sw-page__smart-bar-amount', '8');
    });
});
