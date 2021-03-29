// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Review: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createReviewFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@catalogue: has no access to review module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
        });

        // open review without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-review-list').should('not.exist');

        // see menu without review menu item
        cy.get('.sw-admin-menu__item--sw-catalogue').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-review').should('not.exist');
    });

    it('@catalogue: can view review', () => {
        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'review',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
        });

        // open review
        cy.get(`${page.elements.dataGridRow}--0`)
            .get('.sw-data-grid__cell--title')
            .contains('Bestes Produkt')
            .click();

        // check review values
        cy.get('.sw-review-detail__save-action').should('be.disabled');
    });

    it('@catalogue: can edit review', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-review/*`,
            method: 'patch'
        }).as('saveProperty');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'review',
                role: 'viewer'
            }, {
                key: 'review',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
        });

        // open review
        cy.get(`${page.elements.dataGridRow}--0`)
            .get('.sw-data-grid__cell--title')
            .contains('Bestes Produkt')
            .click();

        cy.get('#sw-field--review-comment').type('My description');

        // Verify updated review
        cy.get('.sw-review-detail__save-action').should('not.be.disabled');
        cy.get('.sw-review-detail__save-action').click();
        cy.wait('@saveProperty').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@catalogue: can delete review', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product-review/*`,
            method: 'delete'
        }).as('deleteData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'review',
                role: 'viewer'
            }, {
                key: 'review',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
        });

        // delete review
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(`${page.elements.modal} p`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify new options in listing
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
