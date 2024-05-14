// / <reference types="Cypress" />

describe('SDK Tests: Notification', ()=> {
    beforeEach(() => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/locale`,
            method: 'POST',
        }).as('searchLocale');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        cy.getSDKiFrame('sw-main-hidden')
            .should('exist');

        cy.wait('@searchLocale')
            .its('response.statusCode')
            .should('equal', 200);

        cy.get('.navigation-list-item__type-plugin')
            .should('exist');

        cy.get('.navigation-list-item__type-plugin')
            .should('have.length.least', 3);
    });

    it('@sdk: dispatch a notification', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.log('Go to extension page');

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Trigger a notification');

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Dispatch a notification')
            .click();

        cy.contains('.sw-alert__title', 'Your title');

        cy.contains('.sw-alert__message', 'Your message');
    });
});
