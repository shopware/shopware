/// <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Snippet set: Test crud operations', () => {
    beforeEach(() => {
        cy.createDefaultFixture('snippet-set')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/snippet/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: create and read snippet set', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        // Create a new snippet set
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-settings-snippet-set-list__action-add').click();
        cy.get(`${page.elements.gridRow}--0.is--inline-editing`).should('be.visible');
        cy.get(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`).type('Snip Snap');
        cy.get(`${page.elements.gridRow}--0 select[name=sw-field--item-baseFile]`)
            .select('messages.en-GB');
        cy.get(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.awaitAndCheckNotification('Snippet set "Snip Snap" has been saved.');
        cy.contains(`${page.elements.gridRow}--0 a`, 'Snip Snap');
    });

    it('@settings: update and read snippet set', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        // Update snippet set
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(`${page.elements.gridRow}--0 a`, 'A Set Name Snippet');
        cy.get(`${page.elements.gridRow}--0`).dblclick();
        cy.get(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`).clear();
        cy.get(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`).type('Nordfriesisch');
        cy.get(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`).should('be.visible');
        cy.get(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.awaitAndCheckNotification('Snippet set "Nordfriesisch" has been saved.');
        cy.get('.is--inline-editing').should('not.exist');
        cy.contains(`${page.elements.gridRow}--0 a`, 'Nordfriesisch');
    });

    it('@settings: delete snippet set', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.contains(`${page.elements.gridRow}--0 a`, 'A Set Name Snippet');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.gridRow}--0`,
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete the snippet set "A Set Name Snippet"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(page.elements.modal).should('not.exist');
        cy.awaitAndCheckNotification('Snippet set has been deleted.');
        cy.get(`${page.elements.gridRow}--2`).should('not.exist');
    });
});
