/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Property: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [{ name: 'Red' }, { name: 'Yellow' }, { name: 'Green' }]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@catalogue: has no access to property module', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

            cy.loginAsUserWithPermissions([
                {
                    key: 'product',
                    role: 'viewer'
                }
            ]).then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/property/index`);
            });

            // open property without permissions
            cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
            cy.get('h1').contains('Access denied');
            cy.get('.sw-property-list').should('not.exist');

            // see menu without property menu item
            cy.get('.sw-admin-menu__item--sw-catalogue').click();
            cy.get('.sw-admin-menu__navigation-list-item.sw-property').should('not.exist');
        });
    });


    it.skip('@catalogue: can view property', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

            const page = new ProductPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'property',
                    role: 'viewer'.skip
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/property/index`);
            });

            // open property
            cy.get(`${page.elements.dataGridRow}--0`)
                .get('.sw-data-grid__cell--name')
                .get('.sw-data-grid__cell-value')
                .contains('Color')
                .click();

            // check property values
            cy.get('.sw-property-detail__save-action').should('be.disabled');
            cy.get('.sw-property-option-list__add-button').should('be.disabled');
            cy.get('.sw-property-option-list__delete-button').should('be.disabled');
        });
    });

    it.skip('@catalogue: can edit property', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: '/api/v*/property-group/*',
                method: 'patch'
            }).as('saveProperty');

            const page = new ProductPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'property',
                    role: 'viewer'
                }, {
                    key: 'property',
                    role: 'editor'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/property/index`);
            });

            // open property
            cy.get(`${page.elements.dataGridRow}--0`)
                .get('.sw-data-grid__cell--name')
                .get('.sw-data-grid__cell-value')
                .contains('Color')
                .click();

            cy.get('#sw-field--propertyGroup-description').type('My description');

            // Verify updated product
            cy.get('.sw-property-option-list__add-button').should('be.disabled');
            cy.get('.sw-property-option-list__delete-button').should('be.disabled');
            cy.get('.sw-property-detail__save-action').should('not.be.disabled');
            cy.get('.sw-property-detail__save-action').click();
            cy.wait('@saveProperty').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });

            cy.get(page.elements.smartBarBack).click();
            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--description`)
                .contains('My description');

        });
    });

    it.skip('@catalogue: can create property', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: `${Cypress.env('apiPath')}/property-group`,
                method: 'post'
            }).as('saveData');

            const page = new ProductPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'property',
                    role: 'viewer'
                }, {
                    key: 'property',
                    role: 'editor'
                }, {
                    key: 'property',
                    role: 'creator'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/property/create`);
            });

            // Add property group

            cy.get('input[name=sw-field--propertyGroup-name]').typeAndCheck('1 Coleur');
            cy.get(page.elements.propertySaveAction).click();

            // Verify property in listing
            cy.wait('@saveData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });
            cy.get(page.elements.smartBarBack).click();
            cy.contains('.sw-data-grid__row', '1 Coleur');
        });
    });

    it.skip('@catalogue: can delete property', () => {
        cy.window().then((win) => {
            if (!win.Shopware.FeatureConfig.isActive('next3722')) {
                return;
            }

            // Request we want to wait for later
            cy.server();
            cy.route({
                url: '/api/v*/property-group/*',
                method: 'delete'
            }).as('deleteData');

            const page = new ProductPageObject();

            cy.loginAsUserWithPermissions([
                {
                    key: 'property',
                    role: 'viewer'
                }, {
                    key: 'property',
                    role: 'deleter'
                }
            ]).then(() => {
                cy.visit(`${Cypress.env('admin')}#/sw/property/index`);
            });

            // open property
            cy.clickContextMenuItem(
                `${page.elements.contextMenu}-item--danger`,
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`
            );
            cy.get(`${page.elements.modal} .sw-property-list__confirm-delete-text`)
                .contains('Are you sure you really want to delete the property "Color"?');

            cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

            // Verify new options in listing
            cy.wait('@deleteData').then((xhr) => {
                expect(xhr).to.have.property('status', 204);
            });
            cy.get(page.elements.modal).should('not.exist');
            cy.get(page.elements.emptyState).should('be.visible');
        });
    });
});
