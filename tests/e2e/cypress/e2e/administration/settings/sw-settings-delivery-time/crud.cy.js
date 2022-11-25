// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Delivery times group: Test crud operations', () => {
    beforeEach(() => {
        cy.loginViaApi().then(() => {
            return cy.createProductFixture();
        }).then(() => {
            return cy.createDefaultFixture('delivery-time');
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/delivery/time/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: Create and read delivery time', { tags: ['pa-customers-orders'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/delivery-time`,
            method: 'POST'
        }).as('saveData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');
        cy.get('.sw-settings-delivery-time-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New delivery time');

        // Fill all fields
        cy.get('#sw-field--deliveryTime-name').type('Very long delivery');
        cy.get('.sw-delivery-time-detail__field-unit')
            .typeSingleSelectAndCheck('Month', '.sw-delivery-time-detail__field-unit');
        cy.get('#sw-field--deliveryTime-min').type('1');
        cy.get('#sw-field--deliveryTime-max').type('2');

        // Save
        cy.get('.sw-settings-delivery-time-detail__save').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Verify successful save
        cy.get('.sw-loader__element').should('not.exist');
        cy.contains('.smart-bar__header h2', 'Very long delivery');

        // Verify created element
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Very long delivery');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Very long delivery')
            .should('be.visible');

        // Verify new delivery time is available in product
        cy.clickMainMenuItem({
            targetPath: '#/sw/product/index',
            mainMenuId: 'sw-catalogue',
            subMenuId: 'sw-product'
        });
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.product-deliverability-form').scrollIntoView();
        cy.get('#deliveryTimeId')
            .typeSingleSelectAndCheck('Very long delivery', '#deliveryTimeId');
    });

    it('@settings: Try to create delivery time with empty required fields', { tags: ['pa-customers-orders'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/delivery-time`,
            method: 'POST'
        }).as('saveEmptyData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');
        cy.get('.sw-settings-delivery-time-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New delivery time');

        // Click save without entering any data
        cy.get('.sw-settings-delivery-time-detail__save').click();

        // Verify 400 Bad request
        cy.wait('@saveEmptyData')
            .its('response.statusCode').should('equal', 400);

        // Check if all empty required fields have error messages
        cy.get('.sw-card__content .sw-field .sw-field__error')
            .should('be.visible')
            .should('contain', 'This field must not be empty.');
    });

    it('@settings: Update and read delivery time', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/delivery-time/*`,
            method: 'PATCH'
        }).as('updateData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Express');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Express').should('be.visible');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify correct detail page
        cy.contains('.smart-bar__header h2', 'Express');

        cy.get('#sw-field--deliveryTime-name').clearTypeAndCheck('Turtle');
        cy.get('.sw-delivery-time-detail__field-unit')
            .typeSingleSelectAndCheck('Week', '.sw-delivery-time-detail__field-unit');

        cy.get('.sw-settings-delivery-time-detail__save').click();

        cy.wait('@updateData')
            .its('response.statusCode').should('equal', 204);

        // Verify updated element
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Turtle');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Turtle').should('be.visible');
    });

    it('@settings: Delete delivery time', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/delivery-time/*`,
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-settings-delivery-time-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Express');
        cy.contains(`${page.elements.dataGridRow}--0`, 'Express').should('be.visible');

        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.contains('.sw-modal__body', 'Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData')
            .its('response.statusCode').should('equal', 204);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('not.exist');
    });
});
