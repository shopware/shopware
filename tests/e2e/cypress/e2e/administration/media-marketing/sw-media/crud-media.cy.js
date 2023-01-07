/**
 * @package content
 */
// / <reference types="Cypress" />

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';

describe('Media: Test crud operations', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @media: "create" via file upload and read medium', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST'
        }).as('saveDataFileUpload');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'POST'
        }).as('saveDataUrlUpload');

        cy.setEntitySearchable('media', ['fileName', 'title']);
        page.uploadImageUsingFileUpload('img/sw-login-background.png');

        cy.wait('@saveDataFileUpload')
            .its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');
    });

    it('@base @media: "create" via file url and read medium', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST'
        }).as('saveDataFileUpload');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=plugin-manager--login`,
            method: 'POST'
        }).as('saveDataUrlUpload');

        cy.setEntitySearchable('media', ['fileName', 'title']);

        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload-v2__button-url-upload',
            '.sw-media-upload-v2__button-context-menu'
        );
        page.uploadImageUsingUrl(`${Cypress.config('baseUrl')}/bundles/administration/static/img/plugin-manager--login.png`);

        cy.wait('@saveDataUrlUpload')
            .its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-media-item .sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);
        cy.get('.sw-media-base-item__name[title="plugin-manager--login.png"]')
            .should('be.visible');
    });

    it('@base @media: update and read medium\'s meta data (uploaded via url)', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        // Request we want to wait for later

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/media/*`,
            method: 'PATCH'
        }).as('saveData');

        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload-v2__button-url-upload',
            '.sw-media-upload-v2__button-context-menu'
        );
        cy.setEntitySearchable('media', ['fileName', 'title']);

        page.uploadImageUsingUrl(`${Cypress.config('baseUrl')}/bundles/administration/static/img/plugin-manager--login.png`);
        cy.get('.sw-media-base-item__name[title="plugin-manager--login.png"]')
            .should('be.visible');
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-base-item__name[title="plugin-manager--login.png"]')
            .first()
            .click();

        // Edit meta data
        cy.get('input[placeholder="Title"]').scrollIntoView().type('Cypress example title');
        cy.get('input[placeholder="Title"]').type('{enter}');

        // Verify meta data
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get('.sw-media-media-item .sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);
        cy.get('input[placeholder="Cypress example title"]').should('be.visible');
    });

    it('@base @media: delete medium', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/media/*`,
            method: 'delete'
        }).as('deleteData');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'POST'
        }).as('saveDataFileUpload');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=plugin-manager--login`,
            method: 'POST'
        }).as('saveDataUrlUpload');

        cy.setEntitySearchable('media', ['fileName', 'title']);

        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload-v2__button-url-upload',
            '.sw-media-upload-v2__button-context-menu'
        );
        page.uploadImageUsingUrl(`${Cypress.config('baseUrl')}/bundles/administration/static/img/plugin-manager--login.png`);

        cy.wait('@saveDataUrlUpload').its('response.statusCode').should('equal', 204);
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-media-item .sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /plugin-manager--login/);

        page.deleteFile('plugin-manager--login.png');
    });
});
