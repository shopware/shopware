// / <reference types="Cypress" />

import MediaPageObject from '../../../../support/pages/module/sw-media.page-object';

describe('Media: Dissolve folder', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('media-folder');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/media/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@media: dissolve folder', { tags: ['pa-content-management'] }, () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`,
            '',
            true
        );

        // Upload image in folder
        cy.contains(page.elements.smartBarHeader, 'A thing to fold about');
        cy.setEntitySearchable('media', ['fileName', 'title']);
        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload-v2__button-url-upload',
            '.sw-media-upload-v2__button-context-menu',
            null,
            '',
            true
        );
        page.uploadImageUsingUrl('http://assets.shopware.com/sw_logo_white.png');
        page.dissolve('sw_logo_white.png');
    });
});
