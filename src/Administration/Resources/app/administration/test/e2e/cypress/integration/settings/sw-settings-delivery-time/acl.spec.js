// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Delivery time: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('delivery-time');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: can view delivery time', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'delivery_times',
                role: 'viewer'
            }
        ]);

        // go to delivery times module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-delivery-time').click();

        cy.get('.sw-settings-delivery-time-list').should('be.visible');

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('Express')
            .click();

        // check if values are visible
        cy.get('#sw-field--deliveryTime-name').should('have.value', 'Express');
        cy.get('#sw-field--deliveryTime-min').should('have.value', '1');
        cy.get('#sw-field--deliveryTime-max').should('have.value', '2');
        cy.get('.sw-delivery-time-detail__field-unit').contains('Day');
    });

    it('@settings: can edit delivery time', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'delivery_times',
                role: 'viewer'
            },
            {
                key: 'delivery_times',
                role: 'editor'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/delivery-time/*',
            method: 'patch'
        }).as('updateDeliveryTime');

        // go to delivery times module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-delivery-time').click();

        // click on third element in grid
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('Express')
            .click();

        // edit name
        cy.get('#sw-field--deliveryTime-name').clear().type('Standard');

        // edit unit
        cy.get('.sw-delivery-time-detail__field-unit')
            .typeSingleSelectAndCheck('Week', '.sw-delivery-time-detail__field-unit');

        // save delivery time
        cy.get(page.elements.deliveryTimeSaveAction).click();

        // Verify creation
        cy.wait('@updateDeliveryTime').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Standard');
        cy.get('.sw-settings-delivery-time-list').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Standard');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.deliveryTimeColumnUnit}`).should('be.visible')
            .contains('Week');
    });

    it('@settings: can create delivery time', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'delivery_times',
                role: 'viewer'
            },
            {
                key: 'delivery_times',
                role: 'editor'
            },
            {
                key: 'delivery_times',
                role: 'creator'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/delivery-time',
            method: 'post'
        }).as('createDeliveryTime');

        // go to delivery times module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-delivery-time').click();

        // Create delivery time
        cy.get('a[href="#/sw/settings/delivery/time/create"]').click();

        // Fill all fields
        cy.get('#sw-field--deliveryTime-name').type('Normal');
        cy.get('.sw-delivery-time-detail__field-unit')
            .typeSingleSelectAndCheck('Week', '.sw-delivery-time-detail__field-unit');
        cy.get('#sw-field--deliveryTime-min').type('2');
        cy.get('#sw-field--deliveryTime-max').type('3');
        cy.get(page.elements.deliveryTimeSaveAction).click();

        // Verify creation
        cy.wait('@createDeliveryTime').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Normal');
        cy.get('.sw-settings-delivery-time-list').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.deliveryTimeColumnName}`).contains('Normal');
    });

    it('@settings: can delete delivery time', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'delivery_times',
                role: 'viewer'
            },
            {
                key: 'delivery_times',
                role: 'deleter'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/delivery-time/*',
            method: 'delete'
        }).as('deleteDeliveryTime');

        // go to delivery times module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-delivery-time').click();

        // filter delivery time via search bar
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Express');

        // Delete delivery time
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify deletion
        cy.wait('@deleteDeliveryTime').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.modal).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.deliveryTimeColumnName}`).should('not.exist');
    });
});
