// / <reference types="Cypress" />

describe('Tax: Test tax-rule listing operations', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('tax');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/tax/index`);
            });
    });
    it('@setting: test the default sorting and page', () => {
        cy.get('.sw-data-grid__row--1 > .sw-data-grid__cell--name > .sw-data-grid__cell-content > .sw-data-grid__cell-value').contains('Standard rate').click();

        cy.testListing({
            sorting: {
                text: 'Country',
                propertyName: 'country.name',
                sortDirection: 'ASC',
                location: 0
            },
            page: 1,
            limit: 25,
            changesUrl: false
        });
    });

    it('@setting: test the sorting and limit function', () => {
        cy.testListing({
            sorting: {
                text: 'Country',
                propertyName: 'country.name',
                sortDirection: 'ASC',
                location: 0
            },
            page: 1,
            limit: 25,
            changesUrl: false
        });

        cy.log('change Sorting direction from ASC to DESC');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            sorting: {
                text: 'Country',
                propertyName: 'country.name',
                sortDirection: 'DESC',
                location: 0
            },
            page: 1,
            limit: 25,
            changesUrl: false
        });

        cy.log('change items per page to 10');
        cy.get('#perPage').select('10');

        cy.testListing({
            sorting: {
                text: 'Country',
                propertyName: 'country.name',
                sortDirection: 'DESC',
                location: 0
            },
            page: 1,
            limit: 10,
            changesUrl: false
        });

        cy.log('go to second page');
        cy.get(':nth-child(2) > .sw-pagination__list-button').click();
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            sorting: {
                text: 'Country',
                propertyName: 'country.name',
                sortDirection: 'DESC',
                location: 0
            },
            page: 2,
            limit: 10,
            changesUrl: false
        });

        cy.log('change sorting to Rate');
        cy.get('.sw-data-grid__cell--2 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            sorting: {
                text: 'Rate',
                propertyName: 'taxRate',
                sortDirection: 'ASC',
                location: 2
            },
            page: 2,
            limit: 10,
            changesUrl: false
        });
    });
});
