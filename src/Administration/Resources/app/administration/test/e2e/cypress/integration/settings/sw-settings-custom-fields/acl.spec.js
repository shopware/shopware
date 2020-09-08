/// <reference types="Cypress" />

describe('Property: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/custom/field/index`);
            });

    });

    it('@customField: has no access to custom field module', () => {
        cy.window().then((win) => {
            console.log(win);

            cy.loginAsUserWithPermissions([
                {
                    key: 'product',
                    role: 'viewer'
                }
            ]).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/custom/field/index`);
            });

            // open custom field without permissions
            cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
            cy.get('h1').contains('Access denied');
            cy.get('.sw-property-list').should('not.exist');

            // see menu without custom field menu item
            cy.get('.sw-admin-menu__item--sw-customField').should('not.exist');
        });
    });

    it.skip('@customField: can view customField', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            const page = new CustomerPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'custom_field',
                    role: 'viewer'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/custom/field/index`);
            });
        });
    });

    it.skip('@customField: can edit custom field', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'custom_field',
                    role: 'viewer'
                }, {
                    key: 'custom_field',
                    role: 'editor'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/custom/field/index`);
            });

        });
    });

    it.skip('@customField: can create custom field', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/custom-field`,
                method: 'post'
            }).as('saveData');

            cy.loginAsUserWithPermissions([
                {
                    key: 'custom_field',
                    role: 'viewer'
                }, {
                    key: 'custom_field',
                    role: 'editor'
                }, {
                    key: 'custom_field',
                    role: 'creator'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/custom/field/create`);
            });

            // Add custom field group


        });
    });

    it.skip('@custom field: can delete custom field', () => {
        cy.window().then((win) => {
            if (!win.Shopware.Feature.isActive('FEATURE_NEXT_3722')) {
                return;
            }

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: '/api/v*/custom-field/*',
                method: 'delete'
            }).as('deleteData');

            cy.loginAsUserWithPermissions([
                {
                    key: 'custom_field',
                    role: 'viewer'
                }, {
                    key: 'custom_field',
                    role: 'deleter'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/custom/field/index`);
            });

            // open custom field
            cy.clickContextMenuItem(
                `${page.elements.contextMenu}-item--danger`,
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );
        });
    });
});
