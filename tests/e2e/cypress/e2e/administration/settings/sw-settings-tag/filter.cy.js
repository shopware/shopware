// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

const uuid = require('uuid/v4');

describe('Tag: Test listing filters', () => {
    beforeEach(() => {
        const taxId = uuid().replace(/-/g, '');
        const categoryId = uuid().replace(/-/g, '');

        cy.createDefaultFixture('tag', {
            name: 'Tag without associations',
        })
            .then(() => {
                cy.createDefaultFixture('tax', {
                    id: taxId,
                });
            })
            .then(() => {
                cy.createDefaultFixture('product', {
                    productNumber: 'RS-11111',
                    taxId,
                    tags: [
                        {
                            name: 'Example tag',
                        },
                    ],
                });
            })
            .then(() => {
                cy.createDefaultFixture('category', {
                    id: categoryId,
                    tags: [
                        {
                            name: 'Example tag 2',
                        },
                    ],
                });
            })
            .then(() => {
                cy.createDefaultFixture('tag', {
                    name: 'Duplicate tag',
                    categories: [{
                        id: categoryId,
                    }],
                });
            })
            .then(() => {
                cy.createDefaultFixture('tag', {
                    name: 'Duplicate tag',
                    categories: [{
                        id: categoryId,
                    }],
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: filter tags', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tag`,
            method: 'POST',
        }).as('loadTags');

        const checkFilterlessListing = () => {
            cy.get('.sw-settings-tag-list__grid').should('be.visible');
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Duplicate tag');
            cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('contain', 'Duplicate tag');
            cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).should('contain', 'Example tag');
            cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--products`).contains(/1(\s)*product/);
            cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--name`).should('contain', 'Example tag 2');
            cy.get(`${page.elements.dataGridRow}--3 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
            cy.get(`${page.elements.dataGridRow}--4 .sw-data-grid__cell--name`).should('contain', 'Tag without associations');
        };

        // Check initial listing
        checkFilterlessListing();

        // Check assigment filter by products
        cy.get('.sw-settings-tag-list__filter-menu-trigger').click();
        cy.get('.sw-settings-tag-list__filter-assignment-select').typeMultiSelectAndCheck(
            'Product',
            '.sw-settings-tag-list__filter-assignment-select',
        );

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Example tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--products`).contains(/1(\s)*product/);
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Reset filter
        cy.get('.sw-settings-tag-list__filter-assignment-select .sw-label__dismiss').click({ force: true });
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);
        checkFilterlessListing();

        // Check duplicate filter
        cy.get('[name="sw-field--duplicateFilter"]').check();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Duplicate tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('contain', 'Duplicate tag');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
        cy.get(`${page.elements.dataGridRow}--2`).should('not.exist');

        // Reset filter
        cy.get('[name="sw-field--duplicateFilter"]').uncheck();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);
        checkFilterlessListing();

        // Check empty filter
        cy.get('[name="sw-field--emptyFilter"]').check();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Tag without associations');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
    });
});
