// / <reference types="Cypress" />

import ShippingPageObject from '../../../support/pages/module/sw-shipping.page-object';

describe('Shipping: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createShippingFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
            });
    });

    it('@package @settings: create and read shipping method', () => {
        const page = new ShippingPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/shipping-method?_response=true',
            method: 'post'
        }).as('saveData');

        // Create shipping method
        cy.get('a[href="#/sw/settings/shipping/create"]').click();
        page.createShippingMethod('Automated test shipping');
        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get(page.elements.smartBarBack).click({ force: true });
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible')
            .contains('Automated test shipping');
    });

    it('@package @settings: update and read shipping method', () => {
        const page = new ShippingPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/shipping-method/*',
            method: 'patch'
        }).as('saveData');

        // Edit base data
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Luftpost');
        cy.clickContextMenuItem(
            '.sw-settings-shipping-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );;
        cy.get('input[name=sw-field--shippingMethod-name]').clearTypeAndCheck('Wasserpost');
        page.createShippingMethodPriceRule();

        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--2 .sw-data-grid__cell--name`).should('be.visible')
            .contains('Wasserpost');
    });

    it('@package @settings: delete shipping method', () => {
        const page = new ShippingPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v1/shipping-method/*',
            method: 'delete'
        }).as('deleteData');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Luftpost');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you really want to delete the shipping method "Luftpost"?');
        cy.get(`${page.elements.modal}__footer button${page.elements.primaryButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.awaitAndCheckNotification('Shipping method "Luftpost" has been deleted.');
    });
});
