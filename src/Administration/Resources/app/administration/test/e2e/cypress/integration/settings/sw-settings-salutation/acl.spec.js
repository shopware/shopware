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
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

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
    });

    it('@settings: can not find salutation item in menu if have not privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            cy.loginAsUserWithPermissions([]);

            // go to salutaion module
            cy.get('.sw-admin-menu__item--sw-settings').click();

            // assert that there is not an salutation setting menu
            cy.get('#sw-settings-salutation').should('not.exist');
        });
    });

    it('@settings: can create a new salutation if have creator privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            const page = new SettingsPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'salutation',
                    role: 'viewer'
                },
                {
                    key: 'salutation',
                    role: 'creator'
                },
                {
                    key: 'salutation',
                    role: 'editor'
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
    });

    it('@settings: can not create a salutation if have privileges which not contain creator privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'salutation',
                    role: 'viewer'
                },
                {
                    key: 'salutation',
                    role: 'editor'
                },
                {
                    key: 'salutation',
                    role: 'deleter'
                }
            ]);

            // go to salutaion module
            cy.get('.sw-admin-menu__item--sw-settings').click();
            cy.get('#sw-settings-salutation').click();

            // assert create salutation button disabled
            cy.get('.sw-settings-salutation-list__create').invoke('css', 'cursor').should('equal', 'not-allowed');
        });
    });

    it('@settings: can edit a salutation if have editor privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

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
            cy.get(`${page.elements.dataGridRow}--0`).should('be.visible').contains('Dear Boss');
        });
    });

    it('@settings: can not edit a salutation if have privileges which not contain editor privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

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

            // go to salutaion module
            cy.get('.sw-admin-menu__item--sw-settings').click();
            cy.get('#sw-settings-salutation').click();

            // click on first element in grid
            cy.get('input.sw-search-bar__input').typeAndCheckSearchField('mrs');

            // interact on option toggle
            cy.get('.sw-data-grid__actions-menu .sw-context-button__button').click({ multiple: true });

            // assert that edit button contain class is--disabled
            cy.get('.sw-entity-listing__context-menu-edit-action').should('have.class', 'is--disabled');
        });
    });

    it('@settings: can delete a salutation if have a deleter privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

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

    it('@settings: can not delete a salutation if have privileges which not contain deleter privilege', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

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

            // go to salutaion module
            cy.get('.sw-admin-menu__item--sw-settings').click();
            cy.get('#sw-settings-salutation').click();


            // click on first element in grid
            cy.get('input.sw-search-bar__input').typeAndCheckSearchField('mrs');

            // interact on option toggle
            cy.get('.sw-data-grid__actions-menu .sw-context-button__button').click();

            // assert that delete button contain class is--disabled
            cy.get('.sw-context-menu-item--danger').should('have.class', 'is--disabled');
        });
    });
});
