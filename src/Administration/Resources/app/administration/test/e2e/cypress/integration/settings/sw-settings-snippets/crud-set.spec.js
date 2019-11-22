// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Snippet set: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('snippet-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/snippet/index`);
            });
    });

    it('@settings: create and read snippet set', () => {
        const page = new SettingsPageObject();

        // Create a new snippet set
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-settings-snippet-set-list__action-add').click();
        cy.get(`${page.elements.gridRow}--0.is--inline-editing`).should('be.visible');
        cy.get(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`).type('Snip Snap');
        cy.get(`${page.elements.gridRow}--0 select[name=sw-field--item-baseFile]`)
            .select('messages.en-GB');
        cy.get(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`).click();
        cy.awaitAndCheckNotification('Snippet set "Snip Snap" has been saved.');
        cy.get(`${page.elements.gridRow}--0 a`).contains('Snip Snap');
    });

    it('@settings: update and read snippet set', () => {
        const page = new SettingsPageObject();

        // Update snippet set
        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.gridRow}--0 a`).contains('A Set Name Snippet');
        cy.get(`${page.elements.gridRow}--0`).dblclick();
        cy.get(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`).clear();
        cy.get(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`).type('Nordfriesisch');
        cy.get(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`).should('be.visible');
        cy.get(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`).click();
        cy.get('.is--inline-editing').should('not.exist');
        cy.get(`${page.elements.gridRow}--0 a`).contains('Nordfriesisch');
    });

    it('@settings: delete snippet set', () => {
        const page = new SettingsPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.gridRow}--0 a`).contains('A Set Name Snippet');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete the snippet set "A Set Name Snippet"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();
        cy.get(page.elements.modal).should('not.exist');
        cy.awaitAndCheckNotification('Snippet set has been deleted.');
        cy.get(`${page.elements.gridRow}--2`).should('not.exist');
    });
});
