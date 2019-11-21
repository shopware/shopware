// / <reference types="Cypress" />delete country

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Basic Informaion: Edit assignments', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/basic/information/index`);
            });
    });

    it('@settings: assign 404 error layout and test rollout', () => {
        const page = new SettingsPageObject();

        cy.createDefaultFixture('cms-page', {}, 'cms-error-page');

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/_action/system-config/batch',
            method: 'post'
        }).as('saveData');

        // Assign 404 layout to all sales channels
        cy.get('.sw-card.sw-system-config__card--1').should('be.visible');
        cy.get('.sw-card.sw-system-config__card--1 .sw-card__title').contains('Shop pages');
        cy.get('.sw-cms-page-select[name="core.basicInformation.404Page"]').scrollIntoView();
        cy.get('.sw-cms-page-select[name="core.basicInformation.404Page"]').should('be.visible');

        cy.get('.sw-cms-page-select[name="core.basicInformation.404Page"]')
            .typeSingleSelectAndCheck(
                '404 Layout',
                '.sw-cms-page-select[name="core.basicInformation.404Page"] .sw-entity-single-select'
            );

        cy.get('.smart-bar__content .sw-button--primary').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get(
                '.sw-cms-page-select[name="core.basicInformation.404Page"] ' +
                        '.sw-entity-single-select__selection-text'
            ).contains('404 Layout');
        });

        //Request storefront
        cy.request({ url: '/non-existent/',  failOnStatusCode: false }).then(request => {
            expect(request).to.have.property('status', 404);
        });

        cy.visit('/non-existent/', { failOnStatusCode: false });

        cy.get('.cms-page .cms-element-text').contains('404 - Not Found');
    });
});
