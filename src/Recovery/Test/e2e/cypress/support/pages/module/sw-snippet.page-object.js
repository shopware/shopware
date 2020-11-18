/* global cy */
import elements from '../sw-general.page-object';

export default class SnippetPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                editSetAction: '.sw-settings-snippet-set-list__edit-set-action',
                snippetSaveAction: '.sw-snippet-detail__save-action',
                countryColumnName: '.sw-country-list__column-name'
            }
        };
    }

    createSnippet(name, translations) {
        cy.get(this.elements.smartBarHeader).contains('New snippet');
        cy.get(this.elements.snippetSaveAction).should('not.be.enabled');

        cy.get('input[name=sw-field--translationKey]').type(name);
        cy.get('.sw-settings-snippet-detail__translation-field--0 input[name=sw-field--snippet-value]')
            .type(translations.de);
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .type(translations.en);

        cy.get(this.elements.snippetSaveAction).should('be.enabled');
        cy.get(this.elements.snippetSaveAction).click();
        cy.get(this.elements.successIcon).should('be.visible');
    }

    openAllSnippetSets() {
        cy.get(this.elements.editSetAction).should('not.be.enabled');
        cy.get('.sw-grid__header input[type=checkbox]').click();
        cy.get(this.elements.editSetAction).should('be.enabled');

        cy.get(this.elements.editSetAction).click();
        cy.get(this.elements.smartBarHeader).contains('Snippets');
    }

    filterSnippets(name, position = 0) {
        cy.get('.icon--default-action-filter').click();
        cy.get('input[name=addedSnippets]').click();
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get(`${this.elements.dataGridRow}--${position}`).should('be.visible');
        cy.get(`${this.elements.dataGridRow}--${position} .sw-data-grid__cell--id`).contains(name);
    }
}
