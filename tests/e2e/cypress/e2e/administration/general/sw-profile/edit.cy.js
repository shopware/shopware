// / <reference types="Cypress" />

describe('Profile module', () => {
    beforeEach(() => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_info/config`,
            method: 'GET',
        }).as('infoCall');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/profile/index`);

        cy.wait('@infoCall');

        cy.contains('.smart-bar__header', 'Your profile');
        cy.contains('.sw-card__title', 'Profile information');
        cy.get('#sw-field--user-username').should('be.visible');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@base @general: profile change email',  { tags: ['pa-system-settings'] }, () => {
        cy.get('#sw-field--email').should('be.visible');
        cy.get('#sw-field--email').scrollIntoView();
        cy.get('#sw-field--email').realHover();
        cy.get('#sw-field--email').click();
        cy.get('#sw-field--email').clear();
        cy.get('#sw-field--email').type('changed@shopware.com');

        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw-modal__title', 'Confirm password');

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
                    .should('not.exist');

                cy.get('#sw-field--email')
                    .should('be.visible')
                    .should('have.value', 'changed@shopware.com');
            });
    });

    it('@general: profile raise a notification for invalid authentication',  { tags: ['pa-system-settings'] }, () => {
        cy.get('.sw-profile__save-action')
            .should('be.visible')
            .click();

        // expect modal to be open
        cy.get('.sw-modal')
            .should('be.visible');
        cy.contains('.sw-modal__title', 'Confirm password');

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

    it('@base @general: profile change avatar',  { tags: ['pa-system-settings'] }, () => {
        cy.get('.sw-media-upload-v2 .sw-media-upload-v2__button')
            .eq(1)
            .click();

        // Add avatar to profile
        cy.get('.sw-media-upload-v2__file-input')
            .attachFile({
                filePath: 'img/sw-test-image.png',
                fileName: 'sw-test-image.png',
                mimeType: 'image/png',
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
        cy.contains('.sw-modal__title', 'Confirm password');

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
                    .should('not.exist');

                cy.get('.sw-media-preview-v2__item')
                    .should('have.attr', 'src')
                    .and('match', /sw-test-image/);
            });
    });
});
