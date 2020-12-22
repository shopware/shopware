/// <reference types="Cypress" />

import ManufacturerPageObject from '../../../support/pages/module/sw-manufacturer.page-object';

describe('Manufacturer: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-manufacturer');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/manufacturer/index`);
            });
    });

    it('@visual: check appearance of basic manufacturer workflow', () => {
        const page = new ManufacturerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-manufacturer/**`,
            method: 'patch'
        }).as('saveData');

        cy.sortListingViaColumn('Manufacturer', 'shopware AG');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('Manufacturer listing', '.sw-data-grid--full-page');

        // Edit base data
        cy.get(`${page.elements.dataGridRow}--0 a`).click();
        cy.get('input[name=name]').clearTypeAndCheck('be.visible');
        cy.get('input[name=name]').clear().type('What does it means?(TM)');
        cy.get('input[name=link]').clear().type('https://google.com/doodles');

        // Take snapshot for visual testing
        cy.takeSnapshot('Manufacturer detail', '.sw-manufacturer-detail');
    });
});
