/// <reference types="Cypress" />

import SnippetPageObject from '../../../../support/pages/module/sw-snippet.page-object';

describe('Snippets: Test crud operations', () => {
    beforeEach(() => {
        cy.createSnippetFixture()
            .then(() => {
                cy.fixture('snippet').as('testSnippet');
            })
            .then(() => {
                cy.authenticate().then((auth) => {
                    cy.request({
                        headers: {
                            Accept: 'application/vnd.api+json',
                            Authorization: `Bearer ${auth.access}`,
                            'Content-Type': 'application/json',
                        },
                        method: 'POST',
                        url: '/api/_info/config-me',
                        qs: {
                            response: true,
                        },
                        body: {
                            'grid.filter.setting-snippet-list': null,
                        },
                    });
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/snippet/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: create, read, update and delete snippet', { tags: ['pa-system-settings'] }, () => {
        const page = new SnippetPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/snippet-set`,
            method: 'post',
        }).as('searchResultCall');

        cy.log('Open a snippet set');
        cy.get(`${page.elements.gridRow}--1 .sw-field__checkbox input`).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(page.elements.editSetAction).should('be.enabled');

        cy.get(page.elements.editSetAction).click();
        cy.contains(page.elements.smartBarHeader, 'Snippets of "BASE en-GB"');

        cy.log('Create a new snippet');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(page.elements.primaryButton).click();

        page.createSnippet('a.Woodech', {
            de: 'Ech',
            en: 'Blach',
        });

        cy.log('Open all snippet sets');
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings-snippet-list__content').should('exist');
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        page.openAllSnippetSets();

        cy.log('Edit snippet');
        cy.get(`${page.elements.dataGridRow}--0`)
            .should('be.visible')
            .contains('a.Woodech');

        cy.clickContextMenuItem(
            '.sw-settings-snippet-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(page.elements.smartBarHeader, 'a.Woodech');

        cy.get('.sw-settings-snippet-detail__translation-field--0 input[name=sw-field--snippet-value]')
            .clear()
            .type('Foo');
        cy.get('.sw-settings-snippet-detail__translation-field--1 input[name=sw-field--snippet-value]')
            .clear()
            .type('Bar');
        cy.get(page.elements.snippetSaveAction).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.icon--regular-checkmark-xs').should('be.visible');
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`)
            .should('be.visible')
            .should('contain', 'Foo')
            .and('contain', 'Bar');

        cy.log('Delete a snippet');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-button--danger').click();
        cy.get('.sw-modal').should('not.exist');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`)
            .should('not.contain', 'Foo')
            .and('not.contain', 'Bar');
    });
});
