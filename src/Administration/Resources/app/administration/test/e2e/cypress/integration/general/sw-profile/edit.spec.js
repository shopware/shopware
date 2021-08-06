/// <reference types="Cypress" />

describe('Profile module', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
                    .then(() => {
                        cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);
                    });
            });
    });

    it('@base @general: profile change email', () => {
        cy.get('#sw-field--email')
            .should('be.visible')
            .click()
            .clear()
            .type('changed@shopware.com');

        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Enter your current password to confirm');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('not.be.visible');

                cy.get('#sw-field--email')
                    .should('be.visible')
                    .should('have.value', 'changed@shopware.com');
            });
    });

    it('@general: profile raise a notification for invalid authentication', () => {
        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Enter your current password to confirm');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('erawpohs');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('be.visible');

                cy.awaitAndCheckNotification('Please submit your current password correctly.');
            });
    });

    it('@base @general: profile change avatar', () => {
        cy.onlyOnFeature('FEATURE_NEXT_6040');

        cy.get('.sw-media-upload-v2 .sw-media-upload-v2__button')
            .first()
            .click()

        // Add avatar to profile
        cy.get('#files')
            .attachFile({
                filePath: 'img/sw-test-image.png',
                fileName: 'sw-test-image.png',
                mimeType: 'image/png'
            });

        cy.get('.sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.get('.sw-modal__title')
            .contains('Enter your current password to confirm');

        cy.get('.sw-modal__footer > .sw-button--primary')
            .should('be.disabled');

        cy.get('.sw-modal__body input[name="sw-field--confirm-password"]')
            .should('be.visible')
            .typeAndCheck('shopware');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content')
            .should('not.be.disabled')
            .click()
            .then(() => {
                cy.get('.sw-modal')
                    .should('not.be.visible');

                cy.get('.sw-media-preview-v2__item')
                    .should('have.attr', 'src')
                    .and('match', /sw-test-image/);
            });
    });
});
