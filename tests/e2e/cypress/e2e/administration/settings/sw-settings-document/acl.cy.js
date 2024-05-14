// / <reference types="Cypress" />
import DocumentPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Settings Documents: Test crud operations with ACL', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
    });

    it('@general: read documents with ACL, but without rights', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.location('hash').should('eq', '#/sw/privilege/error/index');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/detail/e350641b0d1c417d8d10b34821d2d827`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');


        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/detail/e350641b0d1c4`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');
    });

    it('@general: read document with ACL', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new DocumentPageObject();
        cy.loginAsUserWithPermissions([{ key: 'document', role: 'viewer' }]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        });

        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Document');
        cy.contains('.sw-settings-document-list__add-document', 'Add document');

        // check if create and delete button are disabled
        cy.get('.sw-grid__row--0').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').should('have.class', 'is--disabled');
        cy.get('.sw-settings-document-list__add-document').should('have.class', 'sw-button--disabled');

        // navigate to detail page and see if there are values
        cy.get(`${page.elements.gridRow}--3 a`).click();
        cy.get('#sw-field--documentConfig-name').should('have.value', 'invoice').should('be.disabled');
        cy.get('.sw-settings-document-detail__select-type').should('have.class', 'is--disabled');
        cy.get('.sw-settings-document-detail__save-action').should('be.disabled');

        // click save btn and see if anything happens
        cy.get('.sw-settings-document-detail__save-action').should('have.class', 'sw-button--disabled');
        cy.get('.sw-settings-document-detail__save-action').should('be.disabled');
    });

    it('@catalogue: create and read document with ACL', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new DocumentPageObject();

        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' },
            { key: 'document', role: 'creator' },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        });

        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Document');

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/document-base-config`,
            method: 'POST',
        }).as('saveData');
        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Document');

        // check if delete button is disabled
        cy.get('.sw-grid__row--0').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').should('have.class', 'is--disabled');

        // click on link to create a new document
        cy.contains(page.elements.primaryButton, 'Add document').click();
        cy.url().should('contain', '#/sw/settings/document/create');

        // fill out all the required fields
        cy.get('#sw-field--documentConfig-name').typeAndCheck('very Document');
        cy.get('#documentConfigTypes').scrollIntoView()
            .typeSingleSelectAndCheck('Credit note', '#documentConfigTypes');
        cy.get('.sw-document-detail__select-type').typeMultiSelectAndCheck('Storefront');

        // save minimal document
        cy.get('.sw-settings-document-detail__save-action').click();

        // Verify successful create request
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // go back and see if the created document exists
        cy.get(page.elements.smartBarBack).click();
        cy.contains('.sw-settings-document-list-grid', 'very Document');
    });

    it('@catalogue: edit and read documents with ACL', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new DocumentPageObject();

        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/document-base-config/**`,
            method: 'PATCH',
        }).as('saveData');

        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Document');

        // check if create and delete button are disabled
        cy.get('.sw-grid__row--0').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').should('have.class', 'is--disabled');
        cy.get('.sw-settings-document-list__add-document').should('have.class', 'sw-button--disabled');

        // navigate to detail page and update the name of the document
        cy.get(`${page.elements.gridRow}--0 a`).click();
        cy.get('#sw-field--documentConfig-name').clear().typeAndCheck('very Document');

        // save the changed name
        cy.get('.sw-settings-document-detail__save-action').click();

        // verify successful update request
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // go back and see if change persisted
        cy.get(page.elements.smartBarBack).click();
        cy.contains('.sw-settings-document-list-grid', 'very Document');
    });

    it('@catalogue: create, read and then edit document with ACL', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new DocumentPageObject();

        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' },
            { key: 'document', role: 'creator' },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        });

        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Document');

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/document-base-config`,
            method: 'POST',
        }).as('createData');
        cy.contains(`${page.elements.smartBarHeader} > h2`, 'Document');

        // check if delete button is disabled
        cy.get('.sw-grid__row--0').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').should('have.class', 'is--disabled');

        // click on link to create a new document
        cy.contains(page.elements.primaryButton, 'Add document').click();
        cy.url().should('contain', '#/sw/settings/document/create');

        // fill out all the required fields
        cy.get('#sw-field--documentConfig-name').typeAndCheck('very Document');
        cy.get('#documentConfigTypes').scrollIntoView()
            .typeSingleSelectAndCheck('Credit note', '#documentConfigTypes');
        cy.get('.sw-document-detail__select-type').typeMultiSelectAndCheck('Storefront');

        // save minimal document
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/document-base-config-sales-channel`,
            method: 'POST',
        }).as('loadData');
        cy.get('.sw-settings-document-detail__save-action').click();

        // Verify successful create request
        cy.wait('@createData').its('response.statusCode').should('equal', 204);
        // wait for Data to be loaded
        cy.wait('@loadData').its('response.statusCode').should('equal', 200);

        cy.get('#sw-field--documentConfig-name').scrollIntoView();
        cy.get('#sw-field--documentConfig-name').type('1');

        // save the changed name
        cy.intercept({
            url: `${Cypress.env('apiPath')}/document-base-config/**`,
            method: 'PATCH',
        }).as('updateData');

        cy.get('.sw-settings-document-detail__save-action').click();

        // verify successful update request
        cy.wait('@updateData').its('response.statusCode').should('equal', 204);

        // wait for Data to be loaded
        cy.wait('@loadData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        // go back and see if change persisted
        cy.get(page.elements.smartBarBack).click();
        cy.contains('.sw-settings-document-list-grid', 'very Document1');
    });

    it('@catalogue: delete document with ACL', { tags: ['pa-customers-orders', 'VUE3'] }, () => {
        const page = new DocumentPageObject();
        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' },
            { key: 'document', role: 'deleter' },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        });

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/document-base-config/**`,
            method: 'delete',
        }).as('deleteData');

        // Delete Document
        cy.get('.sw-grid__row--3').first().as('row');
        cy.get('.sw-grid__body').children().its('length').then((length) => {
            cy.get('@row').find('.sw-context-button__button').click();
            cy.get('.sw-document-list__delete-action').click();
            cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();
            cy.get(page.elements.modal).should('not.exist');

            // verify successful delete request
            cy.wait('@deleteData').its('response.statusCode').should('equal', 204).then(() => {
                cy.get('.sw-grid__body').children().should('have.length', length - 1);
            });
        });
    });
});

