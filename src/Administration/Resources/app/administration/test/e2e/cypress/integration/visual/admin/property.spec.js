/// <reference types="Cypress" />

import PropertyPageObject from '../../../support/pages/module/sw-property.page-object';

describe('Property: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    sortingType: 'position',
                    options: [{
                        name: 'Red',
                        position: 2
                    }, {
                        name: 'Yellow',
                        position: 3
                    }, {
                        name: 'Green',
                        position: 1
                    }]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/property/index`);
            });
    });

    it('@visual: check appearance of basic property workflow', () => {
        const page = new PropertyPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post'
        }).as('saveData');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.changeElementStyling('.sw-data-grid__cell--options', 'color: #fff');
        cy.takeSnapshot('Property listing', '.sw-property-list');

        // Add option to property group
        cy.clickContextMenuItem(
            '.sw-property-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.cardTitle).contains('Basic information');

        // Take snapshot for visual testing
        cy.sortListingViaColumn('Position', 'Green', '.sw-data-grid__cell--name')
        cy.takeSnapshot('Property detail - Group', '.sw-property-option-list');

        cy.get('.sw-property-option-list').scrollIntoView();
        cy.get('.sw-property-option-list__add-button').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Property detail - Option modal', '.sw-property-option-list');
    });
});
