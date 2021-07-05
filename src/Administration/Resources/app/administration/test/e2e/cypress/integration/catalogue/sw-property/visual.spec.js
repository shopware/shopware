// / <reference types="Cypress" />

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
                cy.openInitialPage(Cypress.env('admin'));
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
        cy.route({
            url: `${Cypress.env('apiPath')}/search/property-group`,
            method: 'post'
        }).as('getData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/media-default-folder`,
            method: 'post'
        }).as('getMediaFolder');

        cy.clickMainMenuItem({
            targetPath: '#/sw/property/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-property'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-property-list__content').should('exist');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--options', 'color: #fff');

        cy.get('.sw-data-grid__cell--options')
            .should('have.css', 'color', 'rgb(255, 255, 255)');
        cy.takeSnapshot('[Property] Listing', '.sw-property-list');

        // Add option to property group
        cy.clickContextMenuItem(
            '.sw-property-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.cardTitle).contains('Basic information');

        // Take snapshot for visual testing
        cy.sortAndCheckListingAscViaColumn('Position', '1', '.sw-data-grid__cell--position');
        cy.contains('.sw-data-grid__row--0 .sw-data-grid__cell--position', '1').should('be.visible');
        cy.contains('.sw-data-grid__row--1 .sw-data-grid__cell--position', '2').should('be.visible');
        cy.contains('.sw-data-grid__row--2 .sw-data-grid__cell--position', '3').should('be.visible');
        cy.takeSnapshot('[Property] Detail, Group', '.sw-property-option-list');

        cy.get('.sw-property-option-list').scrollIntoView();
        cy.get('.sw-property-option-list__add-button').click();

        cy.wait('@getMediaFolder').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Take snapshot for visual testing
        cy.handleModalSnapshot('New value');
        cy.takeSnapshot('[Property] Detail, Option modal', '#sw-field--currentOption-name');
    });
});
