// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Language: Test crud operations', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createLanguageFixture();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/language/index`);
                cy.get('.sw-settings-language-list-grid').should('exist');
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: create and read language', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();
        const productPage = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/language`,
            method: 'POST'
        }).as('saveData');

        cy.get('.sw-settings-language-list').should('be.visible');
        cy.get('a[href="#/sw/settings/language/create"]').click();
        cy.get('.sw-settings-language-detail').should('be.visible');

        // Create language
        cy.get('input[name=sw-field--language-name]').typeAndCheck('Japanese');
        cy.get('.sw-settings-language-detail__select-iso-code')
            .typeSingleSelectAndCheck('ja-JP', '.sw-settings-language-detail__select-iso-code');
        cy.get('.sw-settings-language-detail__select-locale')
            .typeSingleSelectAndCheck('Japanese, Japan', '.sw-settings-language-detail__select-locale');
        cy.get(page.elements.languageSaveAction).click();

        // Verify and check usage of customer-group
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`, 'Japanese');

        // Check if language can be selected as translation
        cy.clickMainMenuItem({
            targetPath: '#/sw/product/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-product'
        });
        cy.get('.sw-product-list-grid').should('exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        productPage.changeTranslation('Japanese', 2);

        cy.contains('.sw-language-info', '"Product name" displayed in the content language "Japanese".');
    });

    it('@settings: update and read language', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/language/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get('.sw-settings-language-list').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--2`
        );
        cy.get('input[name=sw-field--language-name]').clearTypeAndCheck('Kyoto Japanese');
        cy.get(page.elements.languageSaveAction).click();

        // Verify and check usage of customer-group
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`, 'Kyoto Japanese').should('be.visible');
    });

    it('@settings: delete language', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/language/*`,
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-settings-language-list').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete the language "Philippine English"? This will delete all content in this language and can not be undone!');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify and check usage of customer-group
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).should('not.exist');
    });
});
