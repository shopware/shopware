// / <reference types="Cypress" />

const uuid = require('uuid/v4');

describe('Product: Test bulk edit product', () => {
    beforeEach(() => {
        let taxId; let
            currencyId;

        cy.createDefaultFixture('tax')
            .then(() => {
                cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Standard rate',
                    },
                    endpoint: 'tax',
                });
            }).then(tax => {
                taxId = tax.id;

                cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Euro',
                    },
                    endpoint: 'currency',
                });
            })
            .then(currency => {
                currencyId = currency.id;

                cy.authenticate();
            })
            .then(auth => {
                const products = [];
                for (let i = 1; i <= 2; i++) {
                    products.push(
                        {
                            name: `product-${i}`,
                            stock: i,
                            productNumber: uuid().replace(/-/g, ''),
                            taxId: taxId,
                            price: [
                                {
                                    currencyId: currencyId,
                                    net: 42,
                                    linked: false,
                                    gross: 64,
                                },
                            ],
                        },
                    );
                }
                return cy.request({
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${auth.access}`,
                        'Content-Type': 'application/json',
                    },
                    method: 'POST',
                    url: '/api/_action/sync',
                    qs: {
                        response: true,
                    },
                    body: {
                        'write-product': {
                            entity: 'product',
                            action: 'upsert',
                            payload: products,
                        },

                    },
                });
            });
    });

    it('@product: bulk edit product', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('getProduct');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('getUserConfig');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        cy.authenticate();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

        cy.wait('@getProduct')
            .its('response.statusCode').should('equal', 200);

        cy.wait('@getUserConfig');

        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();

        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.sw-data-grid__bulk-selected.bulk-link').click();

        cy.wait('@getUserConfig');

        cy.get('.sw-product-bulk-edit-modal').should('exist');
        cy.get('.sw-modal__footer .sw-button--primary').click();

        cy.contains('.smart-bar__header', 'Bulk edit: 2 products');

        cy.get('.sw-bulk-edit-change-field__container:first .sw-field__checkbox').click();
        cy.get('.sw-text-editor__content-editor').clear().type('Some random description');

        cy.get('.sw-bulk-edit-product__save-action').click();

        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.footer-right .sw-button--primary', 'Apply changes');

        cy.get('.footer-right .sw-button--primary').click();

        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.sw-bulk-edit-save-modal', 'Bulk edit - Success');
        cy.contains('.footer-right .sw-button--primary', 'Close');
        cy.get('.footer-right .sw-button--primary').click();

        cy.get('.sw-bulk-edit-save-modal').should('not.exist');
    });
});
