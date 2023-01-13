// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

const uuid = require('uuid/v4');

describe('Tag: Test crud operations', () => {
    beforeEach(() => {
        const tagId = uuid().replace(/-/g, '');
        const tags = [
            {
                id: tagId,
                name: 'Example tag',
            },
        ];
        const taxId = uuid().replace(/-/g, '');

        cy.createDefaultFixture('tax', {
            id: taxId,
        })
            .then(() => {
                cy.createDefaultFixture('product', {
                    productNumber: 'RS-11111',
                    taxId,
                    tags,
                }).then(() => {
                    cy.createDefaultFixture('product', {
                        productNumber: 'RS-22222',
                        taxId,
                        tags,
                    });
                }).then(() => {
                    cy.createDefaultFixture('product', {
                        productNumber: 'RS-33333',
                        taxId,
                        tags,
                    });
                });
            })
            .then(() => {
                cy.createDefaultFixture('category', { tags });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tag/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: read tag', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        // Check tag listing
        cy.get('.sw-settings-tag-list__grid').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Example tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--products`).contains(/3(\s)*products/);
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
    });

    it('@settings: delete tag', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tag/*`,
            method: 'delete',
        }).as('deleteData');

        cy.get('.sw-settings-tag-list__grid').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete the tag "Example tag"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('not.exist');
    });

    it('@settings: duplicate tag', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/tag/*`,
            method: 'POST',
        }).as('cloneData');

        cy.get('.sw-settings-tag-list__grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-settings-tag-list__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-settings-tag-list__confirm-duplicate-input input').clear().typeAndCheck('Cloned example tag');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();

        cy.wait('@cloneData').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.modal).should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Cloned example tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--products`).contains(/3(\s)*products/);
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('contain', 'Example tag');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--products`).contains(/3(\s)*products/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
    });

    it('@settings: create tag with assignments', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tag`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('loadProducts');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('loadCategories');

        // Create tag
        cy.get('.sw-settings-tag-list__button-create').click();
        cy.get('.sw-settings-tag-detail-modal').should('be.visible');
        cy.get('.sw-settings-tag-detail-modal__tag-name input').typeAndCheck('New tag');

        // Switch to assingments tab
        cy.get('.sw-settings-tag-detail-modal .sw-tabs-item[title="Assignments"]').click();
        cy.wait('@loadProducts').its('response.statusCode').should('equal', 200);

        // assign product
        cy.get(`.sw-settings-tag-detail-assignments__entities-grid ${page.elements.dataGridRow}--0 .sw-field__checkbox`).click();

        // switch to category assignments
        cy.get(`.sw-settings-tag-detail-assignments__associations-grid .associations-grid__row`).contains('Categories').click();
        cy.wait('@loadCategories').its('response.statusCode').should('equal', 200);

        // assign category
        cy.get(`.sw-settings-tag-detail-assignments__entities-grid ${page.elements.dataGridRow}--0 .sw-field__checkbox`).click();

        // save
        cy.get('.sw-settings-tag-detail-modal .sw-modal__footer .sw-button--primary').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.modal).should('not.exist');

        // check tag has been created
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('contain', 'New tag');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--products`).contains(/1(\s)*product/);
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
    });

    it('@settings: edit tag with assignments', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('loadProducts');

        // Edit tag
        cy.get('.sw-settings-tag-list__grid').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-settings-tag-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-settings-tag-detail-modal').should('be.visible');
        cy.get('.sw-settings-tag-detail-modal__tag-name input').clear().typeAndCheck('Edited tag');

        // Switch to assingments tab
        cy.get('.sw-settings-tag-detail-modal .sw-tabs-item[title="Assignments"]').click();
        cy.wait('@loadProducts').its('response.statusCode').should('equal', 200);

        // unassign product
        cy.get(`.sw-settings-tag-detail-assignments__entities-grid ${page.elements.dataGridRow}--0 .sw-field__checkbox`).click();

        // save
        cy.get('.sw-settings-tag-detail-modal .sw-modal__footer .sw-button--primary').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get(page.elements.modal).should('not.exist');

        // check tag has been edited
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('contain', 'Edited tag');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--products`).contains(/2(\s)*products/);
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--categories`).contains(/1(\s)*category/);
    });
});
