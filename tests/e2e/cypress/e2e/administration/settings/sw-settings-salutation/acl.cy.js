/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Salutation: Test acl privileges', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
    });

    it('@settings: can view a list of salutation if have viewer privilege', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/salutation/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // assert that there is an available list of salutations
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
    });

    it('@settings: can create a new salutation if have creator privilege', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer',
            },
            {
                key: 'salutation',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/salutation/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/salutation`,
            method: 'POST',
        }).as('createSalutation');

        // go to create salutation page
        cy.get('.sw-settings-salutation-list__create').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // clear old data and type another one in salutationKey field
        cy.get('#sw-field--salutation-salutationKey')
            .clear()
            .type('Ms');

        // clear old data and type another one in displayName field
        cy.get('#sw-field--salutation-displayName')
            .clear()
            .type('Miss');

        // clear old data and type another one in letterName field
        cy.get('#sw-field--salutation-letterName')
            .clear()
            .type('Dear Miss');

        cy.get('.sw-settings-salutation-detail__save').click();

        // Verify creation
        cy.wait('@createSalutation').its('response.statusCode').should('equal', 204);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Ms');
        cy.get('.sw-version__title').click();

        // assert salutations list is exists and contains new salutation in list
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).contains('Ms').should('be.visible');
    });

    it('@settings: can edit a salutation if have editor privilege', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer',
            },
            {
                key: 'salutation',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/salutation/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/salutation/*`,
            method: 'PATCH',
        }).as('editSalutation');

        // assert that there is an available list of salutations
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--0`).contains('mr').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // clear old data and type another one in letterName field
        cy.get('#sw-field--salutation-letterName')
            .clear()
            .type('Dear Boss');

        // click save salutation button
        cy.get('.sw-settings-salutation-detail__save').click();

        // Verify creation
        cy.wait('@editSalutation').its('response.statusCode').should('equal', 204);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(page.elements.smartBarBack).click();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/**`,
            method: 'post',
        }).as('searchResultCall');

        cy.get('input.sw-search-bar__input').type('Dear Boss').should('have.value', 'Dear Boss');

        cy.wait('@searchResultCall')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-version__title').click();

        // assert salutations list is exists and contains salutation which was edited before in list
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).contains('Dear Boss').should('be.visible');
    });

    it('@settings: can delete a salutation if have a deleter privilege', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer',
            },
            {
                key: 'salutation',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/salutation/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // prepare api to delete salutation
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/salutation/*`,
            method: 'delete',
        }).as('deleteSalutation');

        // assert that there is an available list of salutations
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');

        // click on first element in grid
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('mr');
        cy.get('body').click(0, 0);
        cy.get('.sw-search-bar__results--v2').should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // assert that confirmation modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete this item?');

        // do deleting action
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();


        // call api to delete the salutaion
        cy.wait('@deleteSalutation').its('response.statusCode').should('equal', 204);
    });
});
