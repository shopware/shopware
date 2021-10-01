// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('CMS: Check usage and editing of image elements', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@base @content: use simple image block', () => {
        const page = new MediaPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple image block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Images');
        cy.get('.sw-cms-preview-image').should('be.visible');
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');

        // Upload image
        cy.get(`.sw-cms-slot__config-modal ${page.elements.uploadInput}`)
            .attachFile('img/sw-login-background.png');

        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('#sw-field--element-config-displayMode-value').select('Cover');
        cy.get('.sw-cms-slot__config-modal .sw-button--primary').click();

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-image').should('be.visible');
        cy.get('.cms-image')
            .should('have.attr', 'src')
            .and('match', /sw-login-background/);
    });

    it('@content: use image slider block', () => {
        const page = new MediaPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple image block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Images');
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(10)').scrollIntoView();
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(10)')
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot .sw-cms-slot__settings-action').click();
        cy.get('.sw-cms-slot__config-modal').should('be.visible');

        // Add three slider images
        cy.get(`.sw-cms-slot__config-modal ${page.elements.uploadInput}`)
            .attachFile('img/sw-login-background.png');
        cy.get('.sw-media-preview-v2__item[alt="sw-login-background"]').should('be.visible');

        cy.get(`.sw-cms-slot__config-modal ${page.elements.uploadInput}`)
            .attachFile('img/sw-test-image.png');
        cy.get('.sw-media-preview-v2__item[alt="sw-test-image"]').should('be.visible');

        cy.get(`.sw-cms-slot__config-modal ${page.elements.uploadInput}`)
            .attachFile('img/sw-storefront-en.jpg');
        cy.get('.sw-media-preview-v2__item[alt="sw-storefront-en"]').should('be.visible');

        cy.awaitAndCheckNotification('File has been saved.');
        cy.get('.sw-modal__footer .sw-button--primary').click();


        // Save layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('#tns1-item0 .image-slider-image')
            .should('be.visible')
            .and('have.attr', 'src')
            .and('match', /sw-login-background/);
        cy.get('.icon-arrow-head-right').click();
        cy.get('#tns1-item1 .image-slider-image')
            .should('be.visible')
            .and('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.get('.icon-arrow-head-right').click();
        cy.get('#tns1-item2 .image-slider-image')
            .should('be.visible')
            .and('have.attr', 'src')
            .and('match', /sw-storefront-en/);
    });
});
