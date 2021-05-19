// / <reference types="Cypress" />

import SalesChannelPageObject from '../../../support/pages/module/sw-sales-channel.page-object';

describe('Sales Channel: Test acl', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @general: read sales channel', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer'
            }
        ]);

        cy.get('.sw-admin-menu__sales-channel-item--1').click();
        cy.get('#sw-field--salesChannel-name').should('have.value', 'Storefront');

        cy.onlyOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-tabs-item').eq(2).click();
        });
        cy.skipOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-tabs-item').eq(1).click();
        });

        cy.get('.sw-sales-channel-detail-theme__info-name').contains('Shopware default theme');

        cy.onlyOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-tabs-item').eq(3).click();
        });
        cy.skipOnFeature('FEATURE_NEXT_12437', () => {
            cy.get('.sw-tabs-item').eq(2).click();
        });

        cy.get('#trackingId').should('be.visible');
    });

    it('@general: edit sales channel', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer'
            },
            {
                key: 'sales_channel',
                role: 'editor'
            }
        ]);

        cy.get('.sw-admin-menu__sales-channel-item--1').click();
        cy.get('#sw-field--salesChannel-name').should('have.value', 'Storefront');
        cy.get('#sw-field--salesChannel-name').clearTypeAndCheck('Shopsite');

        cy.get('.sw-sales-channel-detail__save-action').click();
        cy.get('.sw-admin-menu__sales-channel-item--1').contains('Shopsite');
    });

    it('@general: create sales channel', () => {
        const page = new SalesChannelPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer'
            },
            {
                key: 'sales_channel',
                role: 'editor'
            },
            {
                key: 'sales_channel',
                role: 'creator'
            }
        ]);

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/sales-channel`,
            method: 'post'
        }).as('saveData');

        // Open sales channel creation
        cy.get('.sw-admin-menu__headline').contains('Sales Channel');

        cy.get('.sw-admin-menu__headline-action').click();
        cy.get('.sw-sales-channel-modal__title').contains('Add Sales Channel');
        cy.get(`${page.elements.gridRow}--0 .sw-sales-channel-modal-grid__item-name`).click();
        cy.get('.sw-sales-channel-modal__title').contains('Storefront - details');
        cy.get('.sw-sales-channel-modal__add-sales-channel-action').click();

        // Fill in form and save new sales channel
        page.fillInBasicSalesChannelData('1st Epic Sales Channel');

        cy.get(page.elements.salesChannelSaveAction).click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify creation
        cy.get(page.elements.salesChannelNameInput).should('have.value', '1st Epic Sales Channel');
    });

    it('@general: delete sales channel', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'sales_channel',
                role: 'viewer'
            },
            {
                key: 'sales_channel',
                role: 'editor'
            },
            {
                key: 'sales_channel',
                role: 'deleter'
            }
        ]);

        cy.get('.sw-admin-menu__sales-channel-item--1').click();
        cy.get('.sw-sales-channel-detail-base__button-delete').scrollIntoView().click();
        cy.get('.sw-modal__footer .sw-button--danger').click();

        cy.get('.sw-admin-menu__sales-channel-item--0').contains('Headless');
        cy.get('.sw-admin-menu__sales-channel-item--1').should('not.exist');
    });
});

