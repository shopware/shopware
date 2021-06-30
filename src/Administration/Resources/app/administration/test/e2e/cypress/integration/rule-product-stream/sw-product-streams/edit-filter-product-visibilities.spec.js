// / <reference types="Cypress" />

import ProductStreamObject from "../../../support/pages/module/sw-product-stream.page-object";

describe('Dynamic product group: Test product visibilities filter', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    data: {
                        field: 'name',
                        value: 'Storefront'
                    },
                    endpoint: 'sales-channel'
                });
            })
            .then((saleschannel) => {
                return cy.createProductFixture({
                    visibilities: [{
                        visibility: 30,
                        salesChannelId: saleschannel.id
                    }]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/create`);
            });
    });

    it('@catalogue: can see and add product visibilities', () => {
        const page = new ProductStreamObject();

        const search = 'Storefront: Product name';

        cy.get('.sw-product-stream-filter').as('productStreamFilterWithSingleSelect');
        page.fillFilterWithEntitySelect(
            '@productStreamFilterWithSingleSelect',
            {
                field: 'Visibilities.product_visibility',
                operator: 'Is equal to',
                value: 'Storefront'
            }
        );

        cy.get('@productStreamFilterWithSingleSelect').should(($productStreamFilter) => {
            expect($productStreamFilter).to.have.length(1);
            expect($productStreamFilter).to.contain(search);
        });

        cy.get('.sw-condition-and-container__actions--delete').click();

        cy.get('.sw-product-stream-filter').as('productStreamFilterWithMultiSelect');
        page.fillFilterWithEntityMultiSelect(
            '@productStreamFilterWithMultiSelect',
            {
                field: 'Visibilities.product_visibility',
                operator: 'Is equal to any of',
                value: ['Storefront']
            }
        );

        cy.get('@productStreamFilterWithMultiSelect').should(($productStreamFilter) => {
            expect($productStreamFilter).to.have.length(1);
            expect($productStreamFilter).to.contain(search);
        });
    });
});
