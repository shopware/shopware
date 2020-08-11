/// <reference types="Cypress" />

describe('Theme: Test common editing of theme', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
            });
    });

    it('@base @media @content: change theme logo image', { browser: '!firefox' }, () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.contains('.sw-card__title', 'Media').scrollIntoView();
        cy.get('.sw-media-upload-v2')
            .first()
            .contains('Desktop');

        cy.get('.sw-media-upload-v2 .sw-media-upload-v2__remove-icon')
            .first()
            .click();

        // Add image to product
        cy.fixture('img/sw-test-image.png').then(fileContent => {
            cy.get('#files').upload(
                {
                    fileContent,
                    fileName: 'sw-test-image.png',
                    mimeType: 'image/png'
                }, {
                    subjectType: 'input'
                }
            );
        });
        cy.get('.sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-button-process').click();

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw_theme_manager__confirm-save-text')
            .contains('Do you really want to save the changes? This will change the visualization of your shop.');
        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.visit('/');
        cy.get('.img-fluid').should('be.visible');
        cy.get('.img-fluid')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
    });

    it('@base @content: saves theme primary color', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area');

        cy.get('.sw-colorpicker .sw-colorpicker__input').first().clear().typeAndCheck('#000');

        cy.get('.smart-bar__actions .sw-button-process.sw-button--primary').click();
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-colorpicker .sw-colorpicker__input').first().should('have.value', '#000');
        });

        cy.visit('/');
        cy.get('.header-cart-total')
            .should('have.css', 'color', 'rgb(0, 0, 0)');

    });
});
