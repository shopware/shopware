/// <reference types="Cypress" />

import SnippetPageObject from '../../../support/pages/module/sw-snippet.page-object';

describe('Snippets: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createSnippetFixture();
            })
            .then(() => {
                cy.fixture('snippet').as('testSnippet');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/snippet/index`);
            });
    });

    it('@settings: create, read and delete snippets', () => {
        const page = new SnippetPageObject();

        // Open snippet set
        cy.get(`${page.elements.gridRow}--1 .sw-field__checkbox input`).click();
        cy.get(page.elements.editSetAction).should('be.enabled');

        cy.get(page.elements.editSetAction).click();
        cy.get(page.elements.smartBarHeader).contains('Snippets of "BASE en-GB"');

        // Create snippet': (browser) => {
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.primaryButton).click();

        page.createSnippet('a.Woodech', {
            de: 'Ech',
            en: 'Blach'
        });

        // Open all snippet sets
        cy.get(page.elements.smartBarBack).click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-settings-snippet-list__content').should('exist');
        cy.get(page.elements.smartBarBack).click();
        cy.get(page.elements.loader).should('not.exist');
        page.openAllSnippetSets();

        // Filter for and verify snippet to be deleted
        cy.get('.sw-search-bar__input').type('a.Woodech');
        page.filterSnippets('a.Woodech');

        // Delete snippet
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal}__body`).contains('Are you sure you want to delete the snippets');

        cy.get(`${page.elements.modalFooter} button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('not.exist');
    });

    it('@settings: update and read snippets', () => {
        const page = new SnippetPageObject();

        // Open snippet set
        cy.get(`${page.elements.gridRow}--1 .sw-field__checkbox input`).click();
        cy.get(page.elements.editSetAction).should('be.enabled');

        cy.get(page.elements.editSetAction).click();
        cy.get(page.elements.smartBarHeader).contains('Snippets of "BASE en-GB"');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--id`).contains('aWonderful.customSnip');

        // Edit snippet
        cy.clickContextMenuItem(
            '.sw-settings-snippet-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.smartBarHeader).contains('aWonderful.customSnip');
        // sometimes vue renders really slow placeholder values and intercepts our typing… so we ensure that vue filled that value before
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .should('not.have.value', '');
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .clear();
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .type('Mine yours theirs');
        cy.get(page.elements.snippetSaveAction).click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
        cy.get(page.elements.smartBarBack).click();

        // check if it was saved
        cy.get(`${page.elements.dataGridRow}--0`).contains('Mine yours theirs');
    });

    it('@settings: update, read, reset snippets', () => {
        const page = new SnippetPageObject();

        // Open snippet set
        cy.get(`${page.elements.gridRow}--1 .sw-field__checkbox input`).click();
        cy.get(page.elements.editSetAction).should('be.enabled');

        cy.get(page.elements.editSetAction).click();
        cy.get(page.elements.smartBarHeader).contains('Snippets of "BASE en-GB"');

        // Search for snippet
        cy.log('Search for snippet');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('account.addressCreateBtn');
        cy.get('.sw-data-grid-skeleton').should('not.exist');


        // Edit snippet
        cy.log('Edit snippet');
        cy.get(`${page.elements.dataGridRow}--0`).contains('account.addressCreateBtn');

        cy.clickContextMenuItem(
            '.sw-settings-snippet-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(page.elements.smartBarHeader).contains('account.addressCreateBtn');
        // sometimes vue renders really slow placeholder values and intercepts our typing… so we ensure that vue filled that value before
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .should('not.have.value', '');
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .clear();
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .type('Mine yours theirs');
        cy.get(page.elements.snippetSaveAction).click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
        cy.get(page.elements.smartBarBack).click();
        cy.get(page.elements.smartBarBack).click();


        // Reset snippet
        cy.log('Reset snippet');
        page.openAllSnippetSets();
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('account.addressCreateBtn');
        cy.get('.sw-data-grid-skeleton').should('exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).contains('Mine yours theirs');


        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(':nth-child(1) > .sw-grid__cell-content').click();

        cy.get('.sw-button--danger > .sw-button__content').should('not.be.disabled');
        cy.get('.sw-button--danger > .sw-button__content').click();

        // Check that it got reset
        cy.get('.sw-data-grid-skeleton').should('exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('not.contain', 'Mine yours theirs');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'Add address');
    });
});
