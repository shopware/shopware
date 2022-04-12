// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Tag: Test bulk merge', () => {
    beforeEach(() => {
        const taxId = '91b5324352dc4ee58ec320df5dcf2bf4';

        cy.loginViaApi()
            .then(() => {
                cy.createDefaultFixture('tax', {
                    id: taxId
                });
            })
            .then(() => {
                cy.createDefaultFixture('product', {
                    productNumber: 'RS-11111',
                    taxId,
                    tags: [{ id: 'ccc71b4e97644095a1e57748616a5d84', name: 'Example tag 1' }]
                }).then(() => {
                    cy.createDefaultFixture('product', {
                        productNumber: 'RS-22222',
                        taxId,
                        tags: [{ id: '0e6ef8505e36430eb393efa8ae542cb7', name: 'Example tag 2' }]
                    });
                }).then(() => {
                    cy.createDefaultFixture('product', {
                        productNumber: 'RS-33333',
                        taxId,
                        tags: [{ id: '091d255caddc4295a1d5be2bdeef82eb', name: 'Example tag 3' }]
                    });
                });
            })
            .then(() => {
                cy.createDefaultFixture('category', {
                    tags: [{ id: 'ccc71b4e97644095a1e57748616a5d84' }]
                }).then(() => {
                    cy.createDefaultFixture('category', {
                        tags: [{ id: '091d255caddc4295a1d5be2bdeef82eb' }]
                    });
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
            });
    });

    it('@settings: tag bulk merge', () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tag`,
            method: 'POST'
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
        cy.get('.sw-modal .sw-label').contains('Example tag 1');
        cy.get('.sw-modal .sw-label').contains('Example tag 2');
        cy.get('.sw-modal .sw-label').contains('Example tag 3');

        // Enter new name and start merge
        cy.get('#sw-field--duplicateName').typeAndCheck('Merged tag');
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Check new merged tag exists and has combined assignments
        cy.get(page.elements.modal).should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Merged tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--connections`).contains(/3(\s)*products(\s)*,(\s)*2(\s)*categories/);
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
        cy.get('.sw-data-grid__bulk').should('not.exist');
    });
});
