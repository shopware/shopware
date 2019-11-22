// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@package @content: "create" via file upload and read medium', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/_action/media/**/upload?extension=png&fileName=sw-login-background',
            method: 'post'
        }).as('saveData');

        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');
    });

    it('@package @content: update and read medium\'s meta data (uploaded via url)', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/media/*',
            method: 'patch'
        }).as('saveData');

        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload__button-url-upload',
            '.sw-media-upload__button-context-menu'
        );
        page.uploadImageUsingUrl(`${Cypress.config('baseUrl')}/bundles/administration/static/img/sw-login-background.png`);
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .first()
            .click();

        // Edit meta data
        cy.get('input[placeholder="Title"]').scrollIntoView().type('Cypress example title');
        cy.get('input[placeholder="Title"]').type('{enter}');

        // Verify meta data
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('input[placeholder="Cypress example title"]').should('be.visible');
    });

    it('@package @content: delete medium', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/media/*',
            method: 'delete'
        }).as('deleteData');

        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        // Delete image
        cy.get(`${page.elements.mediaItem} ${page.elements.previewItem}`).click();
        cy.get('li.quickaction--delete').click();
        cy.get(`${page.elements.modal}__body`).contains('Are you sure you want to delete "sw-login-background.png"?');
        cy.get('.sw-media-modal-delete__confirm').click();
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('input[placeholder="sw-login-background.png"]').should('not.exist');
    });
});
