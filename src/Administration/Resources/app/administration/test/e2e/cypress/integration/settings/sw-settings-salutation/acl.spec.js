// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Salutation: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view a list of salutation if have viewer privilege', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer'
            }
        ]);

        // go to salutaion module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();

        // assert that there is an available list of salutaions
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
    });

    it('@settings: can create a new salutation if have creator privilege', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer'
            },
            {
                key: 'salutation',
                role: 'creator'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/salutation',
            method: 'post'
        }).as('createSalutation');


        // go to salutaion module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();

        // go to create salutation page
        cy.get('.sw-settings-salutation-list__create').click();

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
        cy.wait('@createSalutation').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Ms');

        // assert salutations list is exists and contains new salutation in list
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible').contains('Ms');
    });

    it('@settings: can edit a salutation if have editor privilege', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer'
            },
            {
                key: 'salutation',
                role: 'editor'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/salutation/*',
            method: 'patch'
        }).as('editSalutation');

        // go to salutaion module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--1`).contains('mr').click();

        // clear old data and type another one in letterName field
        cy.get('#sw-field--salutation-letterName')
            .clear()
            .type('Dear Boss');

        // click save salutation button
        cy.get('.sw-settings-salutation-detail__save').click();

        // Verify creation
        cy.wait('@editSalutation').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Dear Boss');

        // assert salutations list is exists and contains salutation which was edited before in list
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--1`).should('be.visible').contains('Dear Boss');
    });

    it('@settings: can delete a salutation if have a deleter privilege', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'salutation',
                role: 'viewer'
            },
            {
                key: 'salutation',
                role: 'deleter'
            }
        ]);

        // repare api to delete salutation
        cy.server();
        cy.route({
            url: '/api/v*/salutation/*',
            method: 'delete'
        }).as('deleteSalutation');


        // go to salutaion module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();


        // click on first element in grid
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('mr');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // assert that confirmation modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body').contains('Are you sure you want to delete this item?');

        // do deleting action
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();


        // call api to delete the salutaion
        cy.wait('@deleteSalutation').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
