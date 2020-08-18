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
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
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
        cy.takeSnapshot('Property listing', '.sw-property-list');

        // Add option to property group
        cy.clickContextMenuItem(
            '.sw-property-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.cardTitle).contains('Basic information');

        cy.get('.sw-property-option-list').scrollIntoView();
        cy.get('.sw-property-option-list__add-button').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('Property detail - Option modal', '.sw-property-option-list');

        cy.get('input[name=sw-field--currentOption-name]').typeAndCheck('Bleu');
        cy.get('input[name=sw-field--currentOption-position]').type('1');
        cy.get(`${page.elements.modal} .sw-colorpicker .sw-colorpicker__previewWrapper`).click();
        cy.get(`${page.elements.modal} .sw-colorpicker .sw-colorpicker__input`).clear();
        cy.get(`${page.elements.modal} .sw-colorpicker .sw-colorpicker__input`).type('#189eff');
        cy.get(`${page.elements.modal} .sw-colorpicker .sw-colorpicker__input`).type('{enter}');
        cy.get(`.sw-modal__footer ${page.elements.primaryButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Take snapshot for visual testing
        cy.takeSnapshot('Property detail - Group', '.sw-property-option-list');
    });
});
