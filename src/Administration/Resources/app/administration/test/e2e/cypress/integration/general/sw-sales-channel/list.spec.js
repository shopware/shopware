// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test list', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @general: open listing page', { browser: 'chrome' }, () => {
        const page = new SalesChannelPageObject();

        // wait until dashboard has loaded
        cy.get('.sw-dashboard-index__card-headline > h1').contains('Statistics');

        // hover on sales channel headline
        cy.get('.sw-admin-menu__headline').realHover();

        // open sales channel listing
        cy.get('.sw-admin-menu__headline-context-menu').click();
        cy.get('.sw-admin-menu__headline-context-menu-manage-sales-channels').click();

        // check if listing works correctly
        cy.get('.sw-page__smart-bar-amount').contains('2');

        // open sales channel
        cy.get('.sw-data-grid__row--0')
            .find('.sw-context-button__button')
            .click();
        cy.get('.sw-context-menu-item')
            .contains('Edit')
            .click();

        // check if sales channel was opened correctly
        cy.get('.sw-loader').should('not.exist');
        cy.get('.smart-bar__header').contains('Headless');
    });
});

