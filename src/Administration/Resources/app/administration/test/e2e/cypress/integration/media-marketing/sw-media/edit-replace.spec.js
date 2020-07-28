/// <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Replace media', { browser: '!firefox' }, () => {
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

    it('@media: replace media with same file type', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('uploadMedia');


        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('Media');

        // Upload image
        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        // wait until upload finished
        cy.wait('@uploadMedia').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Select uploaded image in media grid
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').click();

        // Start replace action and wait for modal
        cy.get('.sw-media-sidebar__quickaction.quickaction--replace').click();
        cy.get('.sw-media-modal-replace').should('be.visible');

        // Upload new file
        cy.fixture('img/sw-test-image.png').then(fileContent => {
            cy.get(`.sw-media-modal-replace ${page.elements.uploadInput}`).upload(
                {
                    fileContent,
                    fileName: 'sw-test-image.png',
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });

        // Verify image is about to be replaced
        cy.get('.sw-media-modal-replace .sw-media-upload__fallback-icon').should('not.exist');
        cy.get('.sw-media-modal-replace .sw-media-preview-v2__item').should('be.visible');

        // Click replace button and upload new image
        cy.get('.sw-media-modal-replace .sw-media-replace__replace-media-action').click();

        // Verify replace modal is not present
        cy.get('.sw-media-modal-replace').should('not.exist');

        // Select replaced image in media grid
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').click();

        // Verify file name is still the same
        cy.get('.sw-media-sidebar__headline').contains('sw-login-background.png');
        cy.get('.sw-media-quickinfo-metadata-name input').should('have.value', 'sw-login-background');
    });

    it('@media: replace media with different file type', () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('uploadMedia');

        cy.get(page.elements.loader).should('not.exist');
        cy.get(page.elements.smartBarHeader).contains('Media');

        // Upload image
        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        cy.wait('@uploadMedia').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Select uploaded image in media grid
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]').click();

        // Start replace action and wait for modal
        cy.get('.sw-media-sidebar__quickaction.quickaction--replace').click();
        cy.get('.sw-media-modal-replace').should('be.visible');

        // Upload new file with different file type
        cy.fixture('img/sw-storefront-en.jpg').then(fileContent => {
            cy.get(`.sw-media-modal-replace ${page.elements.uploadInput}`).upload(
                {
                    fileContent,
                    fileName: 'sw-storefront-en.jpg',
                    mimeType: 'image/jpg'
                }, {
                    subjectType: 'input'
                }
            );
        });

        // Verify image is about to be replaced
        cy.get('.sw-media-modal-replace .sw-media-upload__fallback-icon').should('not.exist');
        cy.get('.sw-media-modal-replace .sw-media-preview-v2__item').should('be.visible');

        // Verify warning for different file extension is shown
        cy.get('.sw-media-modal-replace .sw-media-modal-replace__file-extension-warning').contains('(jpg)');

        // Click replace button and upload new image
        cy.get('.sw-media-modal-replace .sw-media-replace__replace-media-action').click();

        // Verify replace modal is not present
        cy.get('.sw-media-modal-replace').should('not.exist');

        // Select replaced image in media grid
        cy.get('.sw-media-base-item__name[title="sw-login-background.jpg"]').click();

        // Verify file name is still the same but with different extension
        cy.get('.sw-media-sidebar__headline').contains('sw-login-background.jpg');
        cy.get('.sw-media-quickinfo-metadata-name input').should('have.value', 'sw-login-background');
    });
});
