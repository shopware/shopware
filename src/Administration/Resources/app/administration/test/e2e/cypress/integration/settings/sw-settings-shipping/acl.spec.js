// / <reference types="Cypress" />

import ShippingPageObject from '../../../support/pages/module/sw-shipping.page-object';

describe('Shipping: Test acl privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createShippingFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: read shipping method', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer'
            }
        ]);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);

        // open shipping
        cy.get('.sw-data-grid__cell-value').contains('Luftpost').click();

        // verify fields
        cy.get('#sw-field--shippingMethod-name').should('have.value', 'Luftpost');
        cy.get('.sw-settings-shipping-detail__top-rule').contains('Cart >= 0 (Payment)');
    });

    it('@settings: edit shipping method', () => {
        const page = new ShippingPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer'
            },
            {
                key: 'shipping',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
            method: 'patch'
        }).as('saveData');

        // open shipping
        cy.get('.sw-data-grid__cell-value').contains('Luftpost').click();

        // edit fields
        cy.get('#sw-field--shippingMethod-name').clearTypeAndCheck('Schiffspost');
        cy.get('.sw-settings-shipping-detail__top-rule').typeSingleSelect(
            'All customers',
            '.sw-settings-shipping-detail__top-rule'
        );

        // save shipping method
        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@settings: create shipping method', () => {
        const page = new ShippingPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer'
            },
            {
                key: 'shipping',
                role: 'editor'
            },
            {
                key: 'shipping',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/shipping-method`,
            method: 'post'
        }).as('saveData');

        // Create shipping method
        cy.get('a[href="#/sw/settings/shipping/create"]').click();
        page.createShippingMethod('Automated test shipping');
        cy.get(page.elements.shippingSaveAction).click();

        // Verify shipping method
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click({ force: true });
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible')
            .contains('Automated test shipping');
    });

    it('@settings: delete shipping method', () => {
        const page = new ShippingPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'shipping',
                role: 'viewer'
            },
            {
                key: 'shipping',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/shipping/index`);
        });

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/shipping-method/*`,
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
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.awaitAndCheckNotification('Shipping method "Luftpost" has been deleted.');
    });
});
