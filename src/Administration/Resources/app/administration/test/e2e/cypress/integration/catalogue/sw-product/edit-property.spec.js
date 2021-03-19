/// <reference types='Cypress' />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit property assignment', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.createProductFixture({
                    properties: [
                        {
                            id: 'f1d2554b0ce847cd82f3ac9bd1c0dfba',
                            name: 'red',
                            colorHexCode: '#ff0000',
                            group: {
                                id: 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                                name: 'Color',
                                displayType: 'color'
                            }
                        },
                        {
                            id: 'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
                            name: 'green',
                            colorHexCode: '#00ff00',
                            groupId: 'adf2554b0ce847cd82f3ac9bd1c0dfba'
                        },
                        {
                            id: 'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
                            name: 'blue',
                            colorHexCode: '#0000ff',
                            groupId: 'adf2554b0ce847cd82f3ac9bd1c0dfba'
                        },
                        {
                            id: '3ecc7075aaad49c69c013cb1e58bfc4e',
                            name: 'X',
                            group: {
                                id: '3ecc7075aaad49c69c013cb1e58bfc4e',
                                name: 'size',
                                displayType: 'text'
                            }
                        },
                        {
                            id: '98a3f7d70c4542cbaee991ed16913ef8',
                            name: 'L',
                            groupId: '3ecc7075aaad49c69c013cb1e58bfc4e'
                        },
                        {
                            id: '10d1d7046df74cfe90765b93e13acb47',
                            name: 'M',
                            groupId: '3ecc7075aaad49c69c013cb1e58bfc4e'
                        }
                    ],
                    configuratorSettings: [
                        {
                            id: 'f1d2554b0ce847cd82f3ac9bd1c0dfaa',
                            optionId: 'f1d2554b0ce847cd82f3ac9bd1c0dfba'
                        },
                        {
                            id: 'f1d2554b0ce847cd82f3ac9bd1c0dfab',
                            optionId: 'f1d2554b0ce847cd82f3ac9bd1c0dfbb'
                        },
                        {
                            id: 'f1d2554b0ce847cd82f3ac9bd1c0dfac',
                            optionId: 'f1d2554b0ce847cd82f3ac9bd1c0dfbc'
                        },
                        {
                            id: 'f4fe600c00e64da4941726183dc1da82',
                            optionId: '3ecc7075aaad49c69c013cb1e58bfc4e'
                        },
                        {
                            id: 'f4fe600c00e64da4941726183dc2da83',
                            optionId: 'f1d2554b0ce847cd82f3ac9bd1c0dfba'
                        },
                        {
                            id: '39efd9cadee44eb8a63fa3c211b823a5',
                            optionId: '98a3f7d70c4542cbaee991ed16913ef8'
                        },
                        {
                            id: '45d8e29ced0f49e183abb1046f404188',
                            optionId: '10d1d7046df74cfe90765b93e13acb47'
                        }
                    ]
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: delete property assignment', () => {
        const page = new ProductPageObject();

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-product-detail-page__tabs .sw-tabs-item', 'Specifications').click();

        cy.get('.sw-property-assignment__grid_option_column .sw-property-assignment__grid_option_item').each(($el) => {
            $el.trigger('mouseenter').find('.sw-label__dismiss').trigger('click');
        });

        // Verify deleted properties
        cy.get(page.elements.productSaveAction).click();
        cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
    });
});
