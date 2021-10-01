// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('CMS: Test crud operations of layouts', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.setSalesChannelDomain('Storefront');
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@base @content: create, translate and read layout', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'POST'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('updateData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/cms-page`,
            method: 'POST'
        }).as('reloadPage');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'POST'
        }).as('changeLang');

        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');

        // Fill in basic data
        cy.contains('.sw-cms-create-wizard__page-type', 'Landing page').click();
        cy.get('.sw-cms-create-wizard__title').contains('Choose a section type to start with.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.get('.sw-cms-create-wizard__title').contains('How do you want to label your new layout?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('Laidout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();

        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get('body').then(($body) => {
            if ($body.find('.sw-cms-layout-assignment-modal').length) {
                // Shows layout assignment modal the first time saving after the wizard
                cy.get('.sw-cms-layout-assignment-modal').should('be.visible');

                // Confirm without layout
                cy.get('.sw-cms-layout-assignment-modal__action-confirm').click();
                cy.get('.sw-cms-layout-assignment-modal').should('not.exist');
            }
        });

        cy.wait('@reloadPage').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select.is--disabled').should('not.exist');

        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').contains('Deutsch');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').click();

        cy.wait('@changeLang').its('response.statusCode').should('equal', 200);

        cy.get('body').then(($body) => {
            if ($body.find('.sw-modal').length) {
                // Shows layout assignment modal the first time saving after the wizard
                cy.get('.sw-modal').should('be.visible');

                // Confirm without layout
                cy.get('#sw-language-switch-save-changes-button').click();
                cy.get('.sw-modal').should('be.visible');
            }
        });

        cy.get('.sw-cms-block').should('be.visible');

        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');
        cy.get('.sw-text-editor__content-editor').clear().type('Deutscher Content');

        cy.get('.sw-sidebar__navigation li').first().click();
        cy.get('#sw-field--page-name').clear().typeAndCheck('Deutscher Titel');

        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@updateData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Deutscher Titel');
    });

    it('@base @content: update translation and read layout', () => {
        const page = new MediaPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'POST'
        }).as('changeLang');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get(page.elements.successIcon).should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').contains('Deutsch');
        cy.get('.sw-select-result-list__item-list .sw-select-option--0').click();

        cy.wait('@changeLang')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-text-editor__content-editor')
            .then(() => {
                cy.get('.sw-text-editor__content-editor').clear();
                cy.get('.sw-text-editor__content-editor').type('Deutsch');
            });

        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-text-editor__content-editor').contains('Deutsch');

        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').contains('English');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

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
        cy.get('.cms-block h2').contains('Lorem Ipsum dolor sit amet');
        cy.get('#languagesDropdown-top-bar').click();
        cy.contains('Deutsch').click();
        cy.get('.cms-element-text').contains('Deutsch');
    });
});
