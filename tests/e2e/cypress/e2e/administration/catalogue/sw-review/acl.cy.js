// / <reference types="Cypress" />
/**
 * @package inventory
 */
import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Review: Test ACL privileges', () => {
    beforeEach(() => {
        cy.window()
            .then((win) => {
                win.location.href = 'about:blank';
            })
            .then(() => {
                cy.createReviewFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@catalogue: has no access to review module', { tags: ['pa-content-management'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open review without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.contains('h1', 'Access denied');
        cy.get('.sw-review-list').should('not.exist');

        // see menu without review menu item
        cy.get('.sw-admin-menu__item--sw-catalogue').click();
        cy.get('.sw-admin-menu__navigation-list-item.sw-review').should('not.exist');
    });

    it('@catalogue: can view review', { tags: ['pa-content-management'] }, () => {
        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'review',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open review
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('.sw-data-grid__cell--title a', 'Bestes Produkt')
            .click();

        // check review values
        cy.get('.sw-review-detail__save-action').should('be.disabled');
    });

    it('@catalogue: can edit review', { tags: ['pa-content-management'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-review/*`,
            method: 'PATCH',
        }).as('saveProperty');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'review',
                role: 'viewer',
            }, {
                key: 'review',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open review
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('.sw-data-grid__cell--title a', 'Bestes Produkt')
            .click();

        cy.get('#sw-field--review-comment').type('My description');

        // Verify updated review
        cy.get('.sw-review-detail__save-action').should('not.be.disabled');
        cy.get('.sw-review-detail__save-action').click();
        cy.wait('@saveProperty').its('response.statusCode').should('equal', 204);
    });

    it('@catalogue: can delete review', { tags: ['pa-content-management'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-review/*`,
            method: 'delete',
        }).as('deleteData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'review',
                role: 'viewer',
            }, {
                key: 'review',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/review/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // delete review
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.contains(`${page.elements.modal} p`,
            'Are you sure you want to delete this item?',
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify new options in listing
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
    });
});
