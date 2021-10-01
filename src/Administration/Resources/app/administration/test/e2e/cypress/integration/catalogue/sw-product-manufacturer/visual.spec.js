// / <reference types="Cypress" />

import ManufacturerPageObject from '../../../support/pages/module/sw-manufacturer.page-object';

describe('Manufacturer: Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-manufacturer');
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic manufacturer workflow', () => {
        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'PATCH'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product-manufacturer`,
            method: 'POST'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/manufacturer/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-manufacturer'
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-manufacturer-list__content').should('exist');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Manufacturer] Listing', '.sw-data-grid--full-page');

        // Edit base data
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('.sw-loader').should('not.exist');

        // Take snapshot for visual testing
        cy.get('.sw-media-upload-v2__header .sw-context-button__button').should('be.visible');
        cy.takeSnapshot('[Manufacturer] Detail', '.sw-manufacturer-detail');
    });
});
