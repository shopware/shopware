/// <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test product comparison', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream', {}, 'product-stream-active');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@general: create product comparison sales channel', () => {
        const page = new SalesChannelPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveProductsData');
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/product-export/validate`,
            method: 'post'
        }).as('validate');

        // Edit base data of product
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Upload product image
        if (Cypress.isBrowser({ family: 'chromium' })) {
            // Add image to product
            cy.fixture('img/sw-login-background.png').then(fileContent => {
                cy.get('#files').upload(
                    {
                        fileContent,
                        fileName: 'sw-login-background.png',
                        mimeType: 'image/png'
                    }, {
                        subjectType: 'input'
                    }
                );
            });
            cy.get('.sw-product-image__image img')
                .should('have.attr', 'src')
                .and('match', /sw-login-background/);
            cy.awaitAndCheckNotification('File has been saved.');
        }
        cy.get('.sw-button-process').click();

        // Verify updated product
        cy.wait('@saveProductsData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Open sales channel creation
        cy.get('.sw-admin-menu__headline').contains('Sales Channel');

        cy.get('.sw-admin-menu__headline-action').click();
        cy.get('.sw-sales-channel-modal__title').contains('Add Sales Channel');
        cy.get(`${page.elements.gridRow}--1 .sw-sales-channel-modal-grid__item-name`)
            .contains('Product comparison');
        cy.get(`${page.elements.gridRow}--1 .sw-sales-channel-modal-grid__item-name`).click();
        cy.get('.sw-sales-channel-modal__title').contains('Product comparison - details');
        cy.get('.sw-sales-channel-modal__add-sales-channel-action').click();

        // Fill in form and save new sales channel
        cy.get('#sw-field--templateName').select('Google Shopping (XML)');

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('If you apply the template, existing data in this Sales Channel will be overwritten')
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.awaitAndCheckNotification('Template data has been applied, but not yet saved. Please save this Sales Channel to keep the changes.');

        cy.get(page.elements.salesChannelNameInput).typeAndCheck('A great Product comparison');
        cy.get('.sw-sales-channel-detail__tax-calculation').scrollIntoView();
        cy.get('#sw-field--salesChannel-taxCalculationType-0').click();

        cy.contains('.sw-card__title', 'Storefront Sales Channel').scrollIntoView();
        cy.get('.sw-sales-channel-detail__product-comparison-storefront')
            .typeSingleSelectAndCheck('Storefront', '.sw-sales-channel-detail__product-comparison-storefront');
        cy.get('.sw-sales-channel-detail__product-comparison-domain')
            .typeSingleSelectAndCheck(Cypress.config('baseUrl'), '.sw-sales-channel-detail__product-comparison-domain');

        cy.get('.sw-sales-channel-detail__product-comparison-product-stream').scrollIntoView();
        cy.get('.sw-sales-channel-detail__product-comparison-product-stream')
            .typeSingleSelectAndCheck('2nd Product stream', '.sw-sales-channel-detail__product-comparison-product-stream');

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify creation
        cy.get(page.elements.salesChannelNameInput).should('have.value', 'A great Product comparison');
        cy.get('a[title="Template"]').should('be.visible');
        cy.get('a[title="Template"]').click();
        cy.get('.sw-card__title').contains('Template');

        cy.get('.sw-sales-channel-detail-product-comparison__test-action').scrollIntoView();
        cy.get('.sw-sales-channel-detail-product-comparison__test-action').should('be.visible');
        cy.get('.sw-sales-channel-detail-product-comparison__test-action').click();

        cy.wait('@validate').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.awaitAndCheckNotification('No errors occurred.');
        });
    });
});

