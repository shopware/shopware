// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Tag: Test listing sorting', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.createDefaultFixture('tag', {
                    id: 'ad42199ebf744c82a9c4d4b658b4ffae',
                    categories: [{ name: 'a' }],
                }).then(() => {
                    cy.createDefaultFixture('tag', {
                        id: '60f5499910dc41218f5502f9aebd5404',
                        categories: [{ name: 'a' }, { name: 'b' }],
                    });
                }).then(() => {
                    cy.createDefaultFixture('tag', {
                        id: '1e286b82ce1b40c89fea2c0d803f2f6e',
                        categories: [{ name: 'a' }, { name: 'b' }, { name: 'c' }],
                    });
                }).then(() => {
                    cy.createDefaultFixture('tag', {
                        id: '227b8f48872f41e180a65cd8b313bc9d',
                        categories: [{ name: 'a' }, { name: 'b' }, { name: 'c' }, { name: 'd' }],
                    });
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
            });
    });

    it('@settings: sort tags by assignment count', () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tag`,
            method: 'POST'
        }).as('loadTags');

        // Check assigment filter by products
        cy.get('.sw-settings-tag-list__filter-menu-trigger').click();
        cy.get('.sw-settings-tag-list__filter-assignment-select').typeSingleSelectAndCheck(
            'Categories',
            '.sw-settings-tag-list__filter-assignment-select'
        );

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        // Sort by assignment count descending
        cy.get('.sw-data-grid__cell--sortable.sw-data-grid__cell--1').click();

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/1(\s)*category/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--connections`).contains(/2(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--connections`).contains(/3(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--connections`).contains(/4(\s)*categories/);

        // Sort by assignment count ascending
        cy.get('.sw-data-grid__cell--sortable.sw-data-grid__cell--1').click();

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/4(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--connections`).contains(/3(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--connections`).contains(/2(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--connections`).contains(/1(\s)*category/);
    });
});
