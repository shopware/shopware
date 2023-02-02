// / <reference types="Cypress" />

import ProductStreamObject from '../../../../support/pages/module/sw-product-stream.page-object';

const resultCases = [
    {
        value: 'Red',
        length: 3,
    },
    {
        value: 'Redhouse',
        length: 2,
    },
    {
        value: 'Green',
        length: 1,
    },
    {
        value: 'Test',
        length: 2,
    },
    {
        value: 'Redhouse: Test',
        length: 2,
    },
    {
        value: 'Color: green',
        length: 1,
    },
];

const productManufacture = {
    name: 'Product Manufacturer',
    stock: 1,
    productNumber: 'TEST-123',
    descriptionLong: 'Product description',
    price: [
        {
            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            net: 42,
            linked: false,
            gross: 64,
        },
    ],
    manufacturer: {
        id: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
        name: 'Test Product Manufacturer',
    },
    manufacturerId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
};

describe('Dynamic product group: Test various filters', () => {
    beforeEach(() => {
        cy.createDefaultFixture('product-stream')
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createProductFixture(productManufacture);
            })
            .then(() => {
                return cy.createPropertyFixture({
                    options: [
                        {
                            name: 'Red',
                        },
                        {
                            name: 'Green',
                        },
                    ],
                });
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Redhouse',
                    options: [
                        {
                            name: 'Test 1',
                        },
                        {
                            name: 'Test 2',
                        },
                    ],
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @rule: edit filter', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');
        page.fillFilterWithSelect(
            '@currentProductStreamFilter',
            {
                field: 'Active',
                operator: null,
                value: 'Yes',
            },
        );

        page.clickProductStreamFilterOption(cy.get('.sw-product-stream-filter'), 'Create before');

        cy.get('.sw-product-stream-filter').first().as('first');
        page.fillFilterWithEntitySelect(
            '@first',
            {
                field: 'Product',
                operator: 'Is equal to',
                value: 'Product name',
            },
        );

        page.clickProductStreamFilterOption(cy.get('.sw-product-stream-filter').last(), 'Delete');

        cy.get('.sw-product-stream-filter').should(($productStreamFilter) => {
            expect($productStreamFilter).to.have.length(1);
        });
        cy.contains('button.sw-button', 'Save').click();
        cy.get('button.sw-button .icon--regular-checkmark-xs').should('be.visible');
    });

    it('@base @rule: search and add products with operator "Is equal to any of"', { tags: ['pa-business-ops'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST',
        }).as('getData');

        const page = new ProductStreamObject();

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');

        page.fillFilterWithEntityMultiSelect(
            '@currentProductStreamFilter',
            {
                field: 'Product',
                operator: 'Is equal to any of',
                value: ['Product name'],
            },
        );

        cy.get('body').click(0, 0);
        cy.get('.sw-select-result-list-popover-wrapper').should('not.exist');

        cy.get('@currentProductStreamFilter').within(() => {
            cy.get('.sw-select input').last().type('Manu');

            cy.wait('@getData').its('response.statusCode').should('equal', 200);

            cy.get('.sw-select input').last().type('facturer');

            cy.wait('@getData').its('response.statusCode').should('equal', 200);

            cy.window().then(() => {
                cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper'))
                    .find('.sw-select-result')
                    .contains('Product Manufacturer')
                    .click({ force: true });
            });

            cy.wait('@getData').its('response.statusCode').should('equal', 200);

            cy.get('.sw-select-selection-list').find('[data-id]', { timeout: 1000 }).should('have.length', 2);
        });
    });

    it('@base @rule: Should be able to filter with Manufacturer', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-product-stream-filter').as('productStreamFilterWithMultiSelect');
        page.fillFilterWithEntityMultiSelect(
            '@productStreamFilterWithMultiSelect',
            {
                field: 'Manufacturer',
                operator: 'Is equal to any of',
                value: ['Test Product Manufacturer'],
            },
            true,
        );

        cy.get('.sw-product-stream-filter').should(($productStreamFilter) => {
            expect($productStreamFilter).to.have.length(1);
        });

        cy.get('.sw-product-stream-detail__open_modal_preview')
            .should('be.visible')
            .click();

        cy.get('.sw-modal').should('be.visible');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview__sales-channel-field')
            .typeSingleSelectAndCheck('Storefront', '.sw-product-stream-modal-preview__sales-channel-field');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-product-stream-modal-preview .sw-data-grid__body .sw-data-grid__row')
            .children()
            .contains('.sw-product-variant-info__product-name', 'Product Manufacturer');

        cy.get('.sw-product-stream-modal-preview .sw-button--primary').click();
        cy.get('.sw-product-stream-modal-preview').should('not.exist');

        cy.contains('button.sw-button', 'Save').click();
        cy.get('button.sw-button .icon--regular-checkmark-xs').should('be.visible');
    });

    resultCases.forEach(resultCase => {
        context(`Search property with term ${resultCase.value}`, () => {
            it('@rule: search product property with operator "Is equal to"', () => {
                cy.window().then(() => {
                    const page = new ProductStreamObject();

                    // Verify product stream details
                    cy.clickContextMenuItem(
                        '.sw-entity-listing__context-menu-edit-action',
                        page.elements.contextMenuButton,
                        `${page.elements.dataGridRow}--0`,
                    );
                    cy.get(page.elements.loader).should('not.exist');
                    cy.contains(page.elements.smartBarHeader, '1st Productstream');

                    cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');

                    page.selectFieldAndOperator('@currentProductStreamFilter', 'Properties.Property value', 'Is equal to');

                    cy.get('@currentProductStreamFilter').within(() => {
                        cy.get('.sw-select input').last().clearTypeAndCheck(resultCase.value);

                        cy.window().then(() => {
                            cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper')).should('be.visible');
                            cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper')).find('.sw-select-result').should('have.length', resultCase.length);
                        });
                    });

                    cy.get('.sw-product-stream-filter').should(($productStreamFilter) => {
                        expect($productStreamFilter).to.have.length(1);
                    });
                });
            });

            it('@rule: search product property with operator "Is equal to any of"', () => {
                cy.window().then(() => {
                    const page = new ProductStreamObject();

                    // Verify product stream details
                    cy.clickContextMenuItem(
                        '.sw-entity-listing__context-menu-edit-action',
                        page.elements.contextMenuButton,
                        `${page.elements.dataGridRow}--0`,
                    );
                    cy.get(page.elements.loader).should('not.exist');
                    cy.contains(page.elements.smartBarHeader, '1st Productstream');

                    cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');

                    page.selectFieldAndOperator(
                        '@currentProductStreamFilter',
                        'Properties.Property value',
                        'Is equal to any of',
                    );

                    cy.get('@currentProductStreamFilter').within(() => {
                        cy.get('.sw-select input').last().clearTypeAndCheck(resultCase.value);

                        cy.window().then(() => {
                            cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper')).should('be.visible');
                            cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper')).find('.sw-select-result').should('have.length', resultCase.length);
                        });
                    });

                    cy.get('.sw-product-stream-filter').should(($productStreamFilter) => {
                        expect($productStreamFilter).to.have.length(1);
                    });
                });
            });
        });
    });

    it('@base @rule: Should be able switch operator from negated type to negated type', { tags: ['pa-business-ops'] }, () => {
        const page = new ProductStreamObject();

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get(page.elements.loader).should('not.exist');
        cy.contains(page.elements.smartBarHeader, '1st Productstream');

        cy.get('.sw-product-stream-filter').as('currentProductStreamFilter');

        page.selectFieldAndOperator('@currentProductStreamFilter', 'Name', 'Is equal to');

        cy.get('.sw-product-stream-value__operator-select .sw-single-select')
            .typeSingleSelectAndCheck('Is not equal to', '.sw-product-stream-value__operator-select .sw-single-select');

        cy.get('.sw-product-stream-value__operator-select .sw-single-select')
            .typeSingleSelectAndCheck('Does not contain', '.sw-product-stream-value__operator-select .sw-single-select');
    });
});
