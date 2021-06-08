// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('Promotion: Test ACL privileges', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_13810');
    });

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('promotion');
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/index`);
            });
    });

    it('@acl: can read promotion', () => {
        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'promotion',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/promotion/index`);
        });

        cy.get(`${page.elements.dataGridRow}--0`).contains('Thunder Tuesday');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(page.elements.smartBarHeader)
            .contains('Thunder Tuesday');
        cy.get('#sw-field--promotion-name').should('have.value', 'Thunder Tuesday');
        cy.get('#sw-field--promotion-name').should('be.disabled');

        cy.get(page.elements.primaryButton).should('be.disabled');

        cy.get('.sw-tabs-item').eq(1).click();
        cy.get('.sw-promotion-persona-form__persona-rules .sw-promotion-rule-select')
            .should('have.class', 'is--disabled');
    });

    it('@acl: can edit promotion', () => {
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'patch'
        }).as('patchPromotion');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'promotion',
                role: 'viewer'
            },
            {
                key: 'promotion',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/promotion/index`);
        });

        cy.get(`${page.elements.dataGridRow}--0`).contains('Thunder Tuesday');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(page.elements.smartBarHeader)
            .contains('Thunder Tuesday');
        cy.get('#sw-field--promotion-name').should('be.visible');
        cy.get('#sw-field--promotion-name').should('have.value', 'Thunder Tuesday');
        cy.get('#sw-field--promotion-name').should('not.be.disabled');

        cy.get('#sw-field--promotion-name').clearTypeAndCheck('New promotion name');

        // Add discount
        cy.get(page.elements.loader).should('not.exist');
        cy.get('a[title="Discounts"]').click();
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw-button--ghost').should('be.visible');
        cy.contains('.sw-button--ghost', 'Add discount').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .clear()
            .type('54');

        cy.get('#sw-field--discount-type').select('Fixed item price');

        // Save final promotion
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@patchPromotion').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Verify promotion in Administration
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('New promotion name');
    });

    it('@acl: can delete promotion', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion/*`,
            method: 'delete'
        }).as('deleteData');

        cy.loginAsUserWithPermissions([
            {
                key: 'promotion',
                role: 'viewer'
            },
            {
                key: 'promotion',
                role: 'editor'
            },
            {
                key: 'promotion',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/promotion/index`);
        });

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-listing__confirm-delete-text`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify updated product
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('button[title="Refresh"]').click();
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
