// / <reference types="Cypress" />
import DocumentPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Settings Documents: Test crud operations with ACL', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        });
    });

    it('@base @general: read documents with ACL, but without rights', () => {
        cy.loginAsUserWithPermissions([]);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/detail/e350641b0d1c417d8d10b34821d2d827`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');


        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/detail/e350641b0d1c4`);
        cy.location('hash').should('eq', '#/sw/privilege/error/index');
    });

    it('@base @general: read document with ACL', () => {
        const page = new DocumentPageObject();
        cy.loginAsUserWithPermissions([{ key: 'document', role: 'viewer' }]);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Document');
        cy.get('.sw-settings-document-list__add-document').contains('Add document');

        // check if create and delete button are disabled
        cy.get('.sw-grid__row--0').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').should('have.class', 'is--disabled');
        cy.get('.sw-settings-document-list__add-document').should('have.class', 'sw-button--disabled');

        // navigate to detail page and see if there are values
        cy.get(`${page.elements.gridRow}--3 a`).click();
        cy.get('#sw-field--documentConfig-name').should('have.value', 'storno').should('be.disabled');
        cy.get('.sw-settings-document-detail__select-type').should('have.class', 'is--disabled');
        cy.get('.sw-settings-document-detail__save-action').should('be.disabled');

        // click save btn and see if anything happens
        cy.get('.sw-settings-document-detail__save-action').should('have.class', 'sw-button--disabled');
        cy.get('.sw-settings-document-detail__save-action').should('be.disabled');
    });

    it('@catalogue: create and read document with ACL', () => {
        const page = new DocumentPageObject();

        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' },
            { key: 'document', role: 'creator' }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Document');

        // Request we want to wait for later
        cy.server();
        cy.route({ url: `${Cypress.env('apiPath')}/document-base-config`, method: 'post' }).as('saveData');
        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Document');

        // check if delete button is disabled
        cy.get('.sw-grid__row--0').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').should('have.class', 'is--disabled');

        // click on link to create a new document
        cy.get(page.elements.primaryButton).contains('Add document').click();
        cy.url().should('contain', '#/sw/settings/document/create');

        // fill out all the required fields
        cy.get('#sw-field--documentConfig-name').typeAndCheck('very Document');
        cy.get('#documentConfigTypes').scrollIntoView()
            .typeSingleSelectAndCheck('Credit note', '#documentConfigTypes');
        cy.get('.sw-document-detail__select-type').typeMultiSelectAndCheck('Storefront');

        // save minimal document
        cy.get('.sw-settings-document-detail__save-action').click();

        // Verify successful create request
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // go back and see if the created document exists
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-settings-document-list-grid').contains('very Document');
    });

    it('@catalogue: edit and read documents with ACL', () => {
        const page = new DocumentPageObject();

        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({ url: `${Cypress.env('apiPath')}/document-base-config/**`, method: 'patch' }).as('saveData');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);
        cy.get(`${page.elements.smartBarHeader} > h2`).contains('Document');


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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // go back and see if change persisted
        cy.get(page.elements.smartBarBack).click();
        cy.get('.sw-settings-document-list-grid').contains('very Document');
    });

    it('@catalogue: delete document with ACL', () => {
        const page = new DocumentPageObject();
        cy.loginAsUserWithPermissions([
            { key: 'document', role: 'viewer' },
            { key: 'document', role: 'editor' },
            { key: 'document', role: 'deleter' }
        ]);
        cy.visit(`${Cypress.env('admin')}#/sw/settings/document/index`);

        // Request we want to wait for later
        cy.server();
        cy.route({ url: `${Cypress.env('apiPath')}/document-base-config/**`, method: 'delete' }).as('deleteData');

        // Delete Document
        cy.get('.sw-grid__row--3').first().as('row');
        cy.get('@row').find('.sw-context-button__button').click();
        cy.get('.sw-document-list__delete-action').click();
        cy.get(`${page.elements.modal} ${page.elements.modal}__body p`).contains('Are you sure you want to delete the document "storno"?');
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // verify successful delete request
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.contains('storno').should('not.exist');
    });
});

