// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Language: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createLanguageFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can create and read language', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'language',
                role: 'viewer'
            },
            {
                key: 'language',
                role: 'editor'
            },
            {
                key: 'language',
                role: 'creator'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/language',
            method: 'post'
        }).as('saveData');

        // Go to languages module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-language').click();

        cy.get('.sw-settings-language-list').should('be.visible');
        cy.get('a[href="#/sw/settings/language/create"]').click();

        // Create language
        cy.get('input[name=sw-field--language-name]').typeAndCheck('Japanese');
        cy.get('.sw-settings-language-detail__select-iso-code').typeSingleSelectAndCheck(
            'ja-JP',
            '.sw-settings-language-detail__select-iso-code'
        );
        cy.get('.sw-settings-language-detail__select-locale').typeSingleSelectAndCheck(
            'Japanese, Japan',
            '.sw-settings-language-detail__select-locale'
        );
        cy.get(page.elements.languageSaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).contains('Japanese');
    });

    it('@settings: can update and read language', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'language',
                role: 'viewer'
            },
            {
                key: 'language',
                role: 'editor'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/language/*',
            method: 'patch'
        }).as('saveData');

        // Go to languages module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-language').click();

        cy.get('.sw-settings-language-list').should('be.visible');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--2`
        );
        cy.get('input[name=sw-field--language-name]').clearTypeAndCheck('Kyoto Japanese');
        cy.get(page.elements.languageSaveAction).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).contains('Kyoto Japanese');
    });

    it('@settings: can delete language', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'language',
                role: 'viewer'
            },
            {
                key: 'language',
                role: 'deleter'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/language/*',
            method: 'delete'
        }).as('deleteData');

        // Go to languages module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-language').click();

        cy.get('.sw-settings-language-list').should('be.visible');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--2`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body').contains('Are you sure you want to delete the language "Philippine English"? This will delete all content in this language and can not be undone!');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        // Verify deletion
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.dataGridRow}--2`).should('not.exist');
    });
});
