/// <reference types="Cypress" />

import ProductPageObject from "../../../support/pages/module/sw-product.page-object";

describe('Theme: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
            });
    });

    it('@visual: check appearance of basic theme workflow', () => {
        const page = new ProductPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        // Take snapshot for visual testing
        cy.changeElementStyling(
            ':nth-child(2) > .sw-theme-manager-detail__saleschannel-link > span',
            'color: #fff'
        );
        cy.changeElementStyling(
            ':nth-child(3) > .sw-theme-manager-detail__saleschannel-link > span',
            'color: #fff'
        );
        cy.takeSnapshot('Theme detail', '.sw-theme-manager-detail__area');
    });
});
