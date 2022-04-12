// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Tag: Test listing filters', () => {
    beforeEach(() => {
        const taxId = '91b5324352dc4ee58ec320df5dcf2bf4';

        cy.loginViaApi()
            .then(() => {
                cy.createDefaultFixture('tag', {
                    id: 'ad42199ebf744c82a9c4d4b658b4ffae',
                    name: 'Tag without associations'
                });
            })
            .then(() => {
                cy.createDefaultFixture('tax', {
                    id: taxId
                });
            })
            .then(() => {
                cy.createDefaultFixture('product', {
                    productNumber: 'RS-11111',
                    taxId,
                    tags: [
                        {
                            id: 'f6aa2ff757a24d41b659e476f1c63605',
                            name: 'Example tag'
                        }
                    ]
                });
            })
            .then(() => {
                cy.createDefaultFixture('category', {
                    tags: [
                        {
                            id: 'ccc71b4e97644095a1e57748616a5d84',
                            name: 'Example tag'
                        }
                    ]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
            });
    });

    it('@settings: filter tags', () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tag`,
            method: 'POST'
        }).as('loadTags');

        const checkFilterlessListing = () => {
            cy.get('.sw-settings-tag-list__grid').should('be.visible');
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Example tag');
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/1(\s)*category/);
            cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('contain', 'Example tag');
            cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--connections`).contains(/1(\s)*product/);
            cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).should('contain', 'Tag without associations');
            cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--connections`)
                .invoke('val')
                .then(text => {
                    expect(text).to.equal('');
                });
        };

        // Check initial listing
        checkFilterlessListing();

        // Check assigment filter by products
        cy.get('.sw-settings-tag-list__filter-menu-trigger').click();
        cy.get('.sw-settings-tag-list__filter-assignment-select').typeSingleSelectAndCheck(
            'Products',
            '.sw-settings-tag-list__filter-assignment-select'
        );

        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Example tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/1(\s)*product/);
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Reset filter
        cy.get('.sw-settings-tag-list__filter-assignment-select [data-clearable-button]').click();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);
        checkFilterlessListing();

        // Check duplicate filter
        cy.get('[name="sw-field--duplicateFilter"]').check();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Example tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/1(\s)*(category|product)/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('contain', 'Example tag');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--connections`).contains(/1(\s)*(category|product)/);
        cy.get(`${page.elements.dataGridRow}--2`).should('not.exist');

        // Reset filter
        cy.get('[name="sw-field--duplicateFilter"]').uncheck();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);
        checkFilterlessListing();

        // Check empty filter
        cy.get('[name="sw-field--emptyFilter"]').check();
        cy.wait('@loadTags').its('response.statusCode').should('equal', 200);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Tag without associations');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`)
            .invoke('val')
            .then(text => {
                expect(text).to.equal('');
            });
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
    });
});
