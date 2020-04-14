// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Test crud operations of folders', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('media-folder');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            });
    });

    it('@base @media: create and read folder', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/media-folder',
            method: 'post'
        }).as('saveData');

        // Create folder
        cy.get(page.elements.loader).should('not.exist');
        page.createFolder('1 thing to fold about');
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-media-base-item__name[title="1 thing to fold about"]')
            .should('be.visible');
    });

    it('@base @media: update and read folder using rename', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/media-folder/*',
            method: 'patch'
        }).as('saveData');

        // Edit folder's name
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            '.sw-media-context-item__rename-folder-action',
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );

        cy.get(`${page.elements.folderNameInput}`).clear();
        cy.get(`${page.elements.folderNameInput}`).type('An Edith gets a new name');
        cy.get(`${page.elements.folderNameInput}`).type('{enter}');

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-media-base-item__name[title="An Edith gets a new name"]').should('be.visible');
    });

    it('@base @media: create a subfolder and check configuration', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/media-folder',
            method: 'post'
        }).as('saveData');

        // navigate to subfolder
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-media-folder-item')
            .contains('.sw-media-base-item__name', 'A thing to fold about')
            .click();
        cy.get(page.elements.loader).should('not.exist');
        page.createFolder('new child');

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@base @media: delete folder', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/media-folder/*',
            method: 'delete'
        }).as('deleteData');

        // Delete folder
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );
        cy.get(`${page.elements.modal}__body p`)
            .contains('Are you sure you want to delete the folder "A thing to fold about"?');
        cy.get('.sw-media-modal-delete__confirm').click();

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-media-base-item__name[title="1 thing to fold about"]')
            .should('not.exist');
    });
});
