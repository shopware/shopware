/// <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('Media: Dissolve folder', () => {
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

    it('@media: dissolve folder', () => {
        const page = new MediaPageObject();

        cy.get(page.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            page.elements.showMediaAction,
            page.elements.contextMenuButton,
            `${page.elements.gridItem}--0`
        );

        // Upload image in folder
        cy.get(page.elements.smartBarHeader).contains('A thing to fold about');

        // Upload medium
        cy.clickContextMenuItem(
            '.sw-media-upload-v2__button-url-upload',
            '.sw-media-upload-v2__button-context-menu'
        );
        page.uploadImageUsingUrl('http://assets.shopware.com/sw_logo_white.png');
        page.dissolve('sw_logo_white.png');
    });
});
