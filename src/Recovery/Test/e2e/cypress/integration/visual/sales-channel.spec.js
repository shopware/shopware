/// <reference types="Cypress" />

import SalesChannelPageObject from '../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Visual tests', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                // freezes the system time to Jan 1, 2018
                const now = new Date(2018, 1, 1);
                cy.clock(now);
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic sales channel workflow', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel`,
            method: 'post'
        }).as('saveData');

        // Open sales channel
        cy.log(Cypress.env('testDataUsage'));
        const saleschannel = Cypress.env('testDataUsage') ? 'Footwear' : 'E2E install test';
        cy.contains(saleschannel).click();

        // Take snapshot for visual testing
        cy.changeElementStyling(
            '.sw-version__info',
            'visibility: hidden'
        );
        cy.takeSnapshot('Sales channel detail', '.sw-sales-channel-detail-base');
    });
});

