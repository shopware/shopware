// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Tag: Test listing sorting', () => {
    beforeEach(() => {
        cy.createDefaultFixture('tag', {
            categories: [{ name: 'a' }],
        }).then(() => {
            cy.createDefaultFixture('tag', {
                categories: [{ name: 'a' }, { name: 'b' }],
            });
        }).then(() => {
            cy.createDefaultFixture('tag', {
                categories: [{ name: 'a' }, { name: 'b' }, { name: 'c' }],
            });
        }).then(() => {
            cy.createDefaultFixture('tag', {
                categories: [{ name: 'a' }, { name: 'b' }, { name: 'c' }, { name: 'd' }],
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@settings: sort tags by assignment count', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tag`,
            method: 'POST',
        }).as('loadTags');

        cy.get('.sw-skeleton').should('not.exist');

        cy.get('.sw-data-grid__cell--sortable').contains('Category assignments').scrollIntoView();

        // Sort by assignment count descending
        cy.get('.sw-data-grid__cell--sortable').contains('Category assignments').click();

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--categories`).contains(/2(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--categories`).contains(/3(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--categories`).contains(/4(\s)*categories/);

        // Sort by assignment count ascending
        cy.get('.sw-data-grid__cell--sortable').contains('Category assignments').click();

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/4(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--categories`).contains(/3(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--categories`).contains(/2(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
    });
});
