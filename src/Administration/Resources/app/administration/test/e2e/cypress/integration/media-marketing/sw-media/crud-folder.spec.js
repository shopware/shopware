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
            url: `${Cypress.env('apiPath')}/media-folder`,
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
            url: `${Cypress.env('apiPath')}/media-folder/*`,
            method: 'patch'
        }).as('saveData');

        // Edit folder's name
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            '.sw-media-context-item__rename-folder-action',
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
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
            url: `${Cypress.env('apiPath')}/media-folder`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/media-folder-configuration`,
            method: 'post'
        }).as('postChildConfiguration');
        cy.route({
            url: `${Cypress.env('apiPath')}/media-folder-configuration/**`,
            method: 'delete'
        }).as('deleteChildConfiguration');

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

        page.openChildConfiguration('new child');

        // Uncheck use of inherited parent configuration
        cy.get('[name="sw-field--folder-useParentConfiguration"]').should('be.checked').click();
        // Uncheck keep aspect ratio & check 800x800 thumbnails as test metric
        cy.get('[name="sw-field--configuration-keepAspectRatio"]').should('be.checked').click();
        cy.get('[name="thumbnail-size-800-800-active"]').should('not.be.checked').click();
        // save
        cy.get('.sw-media-modal-folder-settings__confirm').click();

        // Wait for new child configuration to be posted with new data
        cy.wait('@postChildConfiguration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        page.openCurrentFolderConfiguration();

        // Test that parent configuration has not been altered and aspect ratio is still checked
        cy.get('[name="sw-field--configuration-keepAspectRatio"]').should('be.checked');
        cy.get('[name="thumbnail-size-800-800-active"]').should('not.be.checked');
        cy.get('.sw-modal__close').click();

        page.openChildConfiguration('new child');

        // Test that child configuration did persist with unchecked keep aspect ratio & checked 800x800 thumbnails
        cy.get('[name="sw-field--configuration-keepAspectRatio"]').should('not.be.checked');
        cy.get('[name="thumbnail-size-800-800-active"]').should('be.checked').click();
        // Test that use of parent configuration is disabled and enable it again
        cy.get('[name="sw-field--folder-useParentConfiguration"]').should('not.be.checked').click();
        // save
        cy.get('.sw-media-modal-folder-settings__confirm').click();

        // Wait for unused child configuration to be deleted
        cy.wait('@deleteChildConfiguration').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        page.openChildConfiguration('new child');

        // Test that child configuration has been inherited again
        cy.get('[name="sw-field--folder-useParentConfiguration"]').should('be.checked');
        // Test that keep aspect ratio is checked & 800x800 thumbnails unchecked again
        cy.get('[name="sw-field--configuration-keepAspectRatio"]').should('be.checked');
        cy.get('[name="thumbnail-size-800-800-active"]').should('not.be.checked');
    });

    it('@base @media: delete folder', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/media-folder/*`,
            method: 'delete'
        }).as('deleteData');

        // Delete folder
        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).click();
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
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
