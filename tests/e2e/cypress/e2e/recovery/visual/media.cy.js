/// <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';
import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Media: Visual tests', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@visual: check appearance of basic media workflow', { tags: ['pa-system-settings'] }, () => {
        const page = new MediaPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `api/_action/media/**/upload?extension=png&fileName=sw-login-background`,
            method: 'post'
        }).as('saveDataFileUpload');

        page.uploadImageUsingFileUpload('img/sw-login-background.png', 'sw-login-background.png');

        const notification = Cypress.env('locale') === 'en-GB' ?
            'File has been saved' : 'Eine Datei erfolgreich gespeichert';
        cy.wait('@saveDataFileUpload').its('response.statusCode').should('equal', 204);
        cy.get('.sw-media-base-item__name[title="sw-login-background.png"]')
            .should('be.visible');
        cy.get('.sw-media-preview-v2__item').should('have.attr', 'src');
        cy.get('.icon--multicolor-file-thumbnail-broken').should('not.exist');
        cy.get('.icon--multicolor-file-thumbnail-normal').should('not.exist');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Media listing`, '.sw-media-library');
    });
});
