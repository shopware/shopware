/// <reference types="Cypress" />

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

    it.skip('@base @content: create, translate and read layout', () => {
        const page = new MediaPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'post'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('updateData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/cms-page`,
            method: 'post'
        }).as('reloadPage');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'post'
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
        cy.wait('@saveData').then((xhr) => {
            cy.get(page.elements.successIcon).should('be.visible');
            expect(xhr).to.have.property('status', 204);
        });

        cy.wait('@reloadPage').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select.is--disabled').should('not.exist');

        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.wait('@changeLang').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-cms-block').should('be.visible');

        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');
        cy.get('.sw-text-editor__content-editor').clear().type('Deutscher Content');

        cy.get('.sw-sidebar__navigation li').first().click();
        cy.get('#sw-field--page-name').clear().typeAndCheck('Deutscher Titel');

        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@updateData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Deutscher Titel');
    });

    it('@base @content: update translation and read layout', () => {
        const page = new MediaPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'post'
        }).as('changeLang');

        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get(page.elements.successIcon).should('be.visible');
        });
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.wait('@changeLang').then((xhr) => {
            expect(xhr).to.have.property('status', 200);

            cy.get('.sw-text-editor__content-editor')
                .then($target => {
                    const coords = $target[0].getBoundingClientRect();

                    cy.get('.sw-text-editor__content-editor').clear();
                    cy.get('.sw-text-editor__content-editor').type('Deutsch');
                });
        });

        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-text-editor__content-editor').contains('Deutsch');
        });

        cy.get('.sw-cms-toolbar__language-selection .sw-language-switch__select').click();
        cy.get('.sw-select-result-list__item-list').should('be.visible');
        cy.get('.sw-select-result-list__item-list .sw-select-option--1').click();

        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-tree-item__element').contains('Home').click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-cms-list-item__title').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block h2').contains('Lorem Ipsum dolor sit amet');
        cy.get('#languagesDropdown-top-bar').click();
        cy.contains('Deutsch').click();
        cy.get('.cms-element-text').contains('Deutsch');
    });
});
