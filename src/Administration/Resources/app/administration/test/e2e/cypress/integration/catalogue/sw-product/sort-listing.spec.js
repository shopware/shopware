// / <reference types="Cypress" />

describe('Product: Sort grid', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'currency',
                    data: {
                        field: 'isoCode',
                        value: 'GBP'
                    }
                });
            })
            .then(response => {
                const currencyId = response.id;

                return cy.createProductFixture({
                    name: 'Original product',
                    productNumber: 'RS-11111',
                    description: 'Pudding wafer apple pie fruitcake cupcake.',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            net: 55,
                            linked: false,
                            gross: 210
                        },
                        {
                            currencyId,
                            net: 67,
                            linked: false,
                            gross: 67
                        }
                    ]
                });
            })
            .then(response => {
                const currencyId = response.price[1].currencyId;

                return cy.createProductFixture({
                    name: 'Second product',
                    productNumber: 'RS-22222',
                    description: 'Jelly beans jelly-o toffee I love jelly pie tart cupcake topping.',
                    price: [
                        {
                            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                            net: 24,
                            linked: false,
                            gross: 128
                        },
                        {
                            currencyId,
                            net: 12,
                            linked: false,
                            gross: 232
                        }
                    ]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: sort product listing', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post'
        }).as('search');

        // open context menu and display pound
        cy.get('.sw-data-grid__cell-settings .sw-data-grid-settings__trigger').click();
        cy.get('.sw-data-grid__settings-item--9 .sw-field--checkbox').contains('Pound');
        cy.get('.sw-data-grid__settings-item--9 .sw-field--checkbox').click();

        // close context menu
        cy.get('.sw-data-grid__cell-settings .sw-data-grid-settings__trigger').click();

        cy.get('.sw-data-grid-settings').should('not.exist');

        // sort products by gbp - first
        cy.get('.sw-data-grid__cell--9').click({ force: true });

        // Verify search result
        cy.wait('@search').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // check product order
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-data-grid__cell--9').contains('Pound');

        // check order when pound arrow is up
        cy.get('.sw-data-grid__sort-indicator').should('be.visible');
        cy.get('.icon--small-arrow-small-up').should('be.visible');

        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Original product');
        cy.get('.sw-data-grid__row--1 .sw-data-grid__cell--name').contains('Second product');

        // sort products by gbp
        cy.get('.sw-data-grid__cell--9').click({ force: true });

        // Verify search result
        cy.wait('@search').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-data-grid__cell--9').contains('Pound');

        // check order when pound arrow is down
        cy.get('.sw-data-grid__sort-indicator').should('be.visible');
        cy.get('.icon--small-arrow-small-down').should('be.visible');

        cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Second product');
        cy.get('.sw-data-grid__row--1 .sw-data-grid__cell--name').contains('Original product');
    });
});
