// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Delivery times group: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createProductFixture();
        }).then(() => {
            return cy.createDefaultFixture('delivery-time');
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/delivery/time/index`);
            });
    });

    it('@settings: Create and read delivery time', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/delivery-time`,
            method: 'post'
        }).as('saveData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');
        cy.get('.sw-settings-delivery-time-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New delivery time');

        // Fill all fields
        cy.get('#sw-field--deliveryTime-name').type('Very long delivery');
        cy.get('.sw-delivery-time-detail__field-unit')
            .typeSingleSelectAndCheck('Month', '.sw-delivery-time-detail__field-unit');
        cy.get('#sw-field--deliveryTime-min').type('1');
        cy.get('#sw-field--deliveryTime-max').type('2');

        // Save
        cy.get('.sw-settings-delivery-time-detail__save').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify successful save
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.smart-bar__header h2').contains('Very long delivery');

        // Verify created element
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Very long delivery');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Very long delivery');

        // Verify new delivery time is available in product
        cy.clickMainMenuItem({
            targetPath: '#/sw/product/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-product'
        });

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.product-deliverability-form').scrollIntoView();
        cy.get('#deliveryTimeId')
            .typeSingleSelectAndCheck('Very long delivery', '#deliveryTimeId');
    });

    it('@settings: Try to create delivery time with empty required fields', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/delivery-time`,
            method: 'post'
        }).as('saveEmptyData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');
        cy.get('.sw-settings-delivery-time-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New delivery time');

        // Click save without entering any data
        cy.get('.sw-settings-delivery-time-detail__save').click();

        // Verify 400 Bad request
        cy.wait('@saveEmptyData').then((xhr) => {
            expect(xhr).to.have.property('status', 400);
        });

        // Check if all empty required fields have error messages
        cy.get('.sw-card__content .sw-field .sw-field__error')
            .should('be.visible')
            .should('contain', 'This value should not be blank');
    });

    it('@settings: Update and read delivery time', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/delivery-time/*`,
            method: 'patch'
        }).as('updateData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Express');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Express');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify correct detail page
        cy.get('.smart-bar__header h2').contains('Express');

        cy.get('#sw-field--deliveryTime-name').clearTypeAndCheck('Turtle');
        cy.get('.sw-delivery-time-detail__field-unit')
            .typeSingleSelectAndCheck('Week', '.sw-delivery-time-detail__field-unit');

        cy.get('.sw-settings-delivery-time-detail__save').click();

        cy.wait('@updateData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify updated element
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Turtle');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Turtle');
    });

    it('@settings: Delete delivery time', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/delivery-time/*`,
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Express');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Express');

        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('not.exist');
    });
});
