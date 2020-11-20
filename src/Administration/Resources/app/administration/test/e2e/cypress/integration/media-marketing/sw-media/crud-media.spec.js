/// <reference types="Cypress" />

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

    it('@base @media: "create" via file upload and read medium', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('saveDataFileUpload');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'post'
        }).as('saveDataUrlUpload');

            page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

            cy.wait('@saveDataFileUpload').then((xhr) => {
                cy.awaitAndCheckNotification('File has been saved.');
                expect(xhr).to.have.property('status', 204);
            });
            cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
                .should('be.visible');
    });

    it('@base @media: "create" via file url and read medium', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('saveDataFileUpload');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'post'
        }).as('saveDataUrlUpload');

            // Upload medium
            cy.clickContextMenuItem(
                '.sw-media-upload-v2__button-url-upload',
                '.sw-media-upload-v2__button-context-menu'
            );
            page.uploadImageUsingUrl('http://assets.shopware.com/sw_logo_white.png');

            cy.wait('@saveDataUrlUpload').then((xhr) => {
                cy.awaitAndCheckNotification('File has been saved.');
                expect(xhr).to.have.property('status', 204);
                cy.get('.sw-media-media-item .sw-media-preview-v2__item')
                    .should('have.attr', 'src')
                    .and('match', /sw_logo_white/);
            });
            cy.get('.sw-media-base-item__name[title="sw_logo_white.png"]')
                .should('be.visible');
    });

    it('@base @media: update and read medium\'s meta data (uploaded via url)', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/media/*`,
            method: 'patch'
        }).as('saveData');

        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload-v2__button-url-upload',
            '.sw-media-upload-v2__button-context-menu'
        );
        page.uploadImageUsingUrl('http://assets.shopware.com/sw_logo_white.png');
        cy.get('.sw-media-base-item__name[title="sw_logo_white.png"]')
            .should('be.visible');
        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-media-base-item__name[title="sw_logo_white.png"]')
            .first()
            .click();

        // Edit meta data
        cy.get('input[placeholder="Title"]').scrollIntoView().type('Cypress example title');
        cy.get('input[placeholder="Title"]').type('{enter}');

        // Verify meta data
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);

            cy.get('.sw-media-media-item .sw-media-preview-v2__item')
                .should('have.attr', 'src')
                .and('match', /sw_logo_white/);
        });
        cy.get('input[placeholder="Cypress example title"]').should('be.visible');
    });

    it('@base @media: delete medium', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/media/*`,
            method: 'delete'
        }).as('deleteData');
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('saveDataFileUpload');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw_logo_white`,
            method: 'post'
        }).as('saveDataUrlUpload');

            // Upload medium
            cy.clickContextMenuItem(
                '.sw-media-upload-v2__button-url-upload',
                '.sw-media-upload-v2__button-context-menu'
            );
            page.uploadImageUsingUrl('http://assets.shopware.com/sw_logo_white.png');

        cy.wait('@saveDataUrlUpload').then((xhr) => {
                cy.awaitAndCheckNotification('File has been saved.');
                expect(xhr).to.have.property('status', 204);
                cy.get('.sw-media-media-item .sw-media-preview-v2__item')
                    .should('have.attr', 'src')
                    .and('match', /sw_logo_white/);
            });
            page.deleteFile('sw_logo_white.png');
    });
});
