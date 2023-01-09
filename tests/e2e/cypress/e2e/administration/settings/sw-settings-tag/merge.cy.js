// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

const uuid = require('uuid/v4');

describe('Tag: Test bulk merge', () => {
    beforeEach(() => {
        const taxId = uuid().replace(/-/g, '');
        const tagIdA = uuid().replace(/-/g, '');
        const tagIdB = uuid().replace(/-/g, '');

        cy.createDefaultFixture('tax', {
            id: taxId,
        })
            .then(() => {
                cy.createDefaultFixture('product', {
                    productNumber: 'RS-11111',
                    taxId,
                    tags: [{ id: tagIdA, name: 'Example tag 1' }],
                }).then(() => {
                    cy.createDefaultFixture('product', {
                        productNumber: 'RS-22222',
                        taxId,
                        tags: [{ name: 'Example tag 2' }],
                    });
                }).then(() => {
                    cy.createDefaultFixture('product', {
                        productNumber: 'RS-33333',
                        taxId,
                        tags: [{ id: tagIdB, name: 'Example tag 3' }],
                    });
                });
            })
            .then(() => {
                cy.createDefaultFixture('category', {
                    tags: [{ id: tagIdA }],
                }).then(() => {
                    cy.createDefaultFixture('category', {
                        tags: [{ id: tagIdB }],
                    });
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: tag bulk merge', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tag`,
            method: 'POST',
        }).as('saveData');

        // Select tags
        cy.get('.sw-settings-tag-list__grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--selection input`).check();
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--selection input`).check();
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--selection input`).check();

        // Open bulk merge modal
        cy.get('.sw-data-grid__bulk').should('exist');
        cy.get('.sw-data-grid__bulk .link:not(.link-danger)').click();
        cy.get('.sw-modal').should('be.visible');
        cy.contains('.sw-modal .sw-label', 'Example tag 1');
        cy.contains('.sw-modal .sw-label', 'Example tag 2');
        cy.contains('.sw-modal .sw-label', 'Example tag 3');

        // Enter new name and start merge
        cy.get('#sw-field--duplicateName').typeAndCheck('Merged tag');
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Check new merged tag exists and has combined assignments
        cy.get(page.elements.modal).should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Merged tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--products`).contains(/3(\s)*products/);
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/2(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
        cy.get('.sw-data-grid__bulk').should('not.exist');
    });
});
