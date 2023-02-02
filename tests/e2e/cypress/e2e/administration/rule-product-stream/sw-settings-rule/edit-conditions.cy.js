// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

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

describe('Rule builder: Test crud operations', () => {
    beforeEach(() => {
        cy.createDefaultFixture('rule').then(() => {
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
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@rule: edit rule conditions', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').as('condition-general');

            page.createBasicSelectCondition({
                selector: '@condition-general',
                type: 'Item with free shipping',
                operator: null,
                value: 'No',
            });

            cy.get('button.sw-button').contains('Add AND condition').click();
            cy.get('.sw-condition').should('have.length', 2);

            cy.get('.sw-condition').eq(1).as('second-condition');
            page.createBasicInputCondition({
                selector: '@second-condition',
                type: 'Grand total',
                operator: 'Is greater than',
                inputName: 'amount',
                value: '100',
            });

            cy.get('@second-condition').within(() => {
                cy.get('.sw-condition__context-button').click();
            });
        });

        cy.get('.sw-context-menu__content').should('be.visible');
        cy.get('.sw-context-menu__content').contains('Create before').click();

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').should('have.length', 3);

            page.createBasicSelectCondition({
                selector: '@second-condition',
                type: 'Customer group',
                operator: 'Is none of',
                value: 'Standard customer group',
            });

            cy.get('@second-condition').within(() => {
                cy.get('.sw-condition__context-button').click();
            });
        });

        cy.get('.sw-context-menu__content').should('be.visible');
        cy.get('.sw-context-menu__content').contains('Create after').click();

        cy.get('@first-and-container').within(() => {
            cy.get('.sw-condition').should('have.length', 4);

            cy.get('.sw-condition').eq(2).as('third-condition');
            page.createBasicSelectConditionFromSearch({
                selector: '@third-condition',
                type: 'Billing address: Country',
                operator: 'Is none of',
                value: 'Australia',
            });
        });

        cy.get('.sw-condition-tree .sw-condition-or-container button.sw-button')
            .contains('Add OR condition')
            .click();

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .eq(1).as('second-and-container');
        cy.get('@second-and-container').should('be.visible');

        cy.get('@second-and-container').within(() => {
            page.createBasicSelectCondition({
                selector: '.sw-condition',
                type: 'Commercial customer',
                operator: null,
                value: 'Yes',
            });

            cy.get('.sw-condition-and-container__actions--sub').click();
            cy.get('.sw-condition').should('have.length', 2);

            cy.get('.sw-condition .sw-condition__context-button').first().click();
        });

        cy.get('.sw-context-menu').contains('Delete').click();

        cy.get('.sw-condition').should('have.length', 5);
        cy.get('@second-and-container')
            .children()
            .should('have.length', 2)
            .first()
            .should('have.class', 'sw-condition-or-container');

        cy.get('@second-and-container').within(() => {
            cy.get('.sw-condition-and-container__actions button.sw-button')
                .contains('Delete container')
                .click();
        });
        cy.get('@second-and-container').should('not.exist');

        cy.get('.sw-condition-tree button').contains('Delete all').click();

        cy.get('.sw-condition-or-container').should('have.length', 1);
        cy.get('.sw-condition-and-container').should('have.length', 1);
        cy.get('.sw-condition').should('have.length', 1);

        cy.get('button.sw-button').contains('Save').click();

        cy.awaitAndCheckNotification('An error occurred while saving rule');
    });

    resultCases.forEach(resultCase => {
        context(`Search property with term ${resultCase.value}`, () => {
            it('@rule: search property', { tags: ['pa-business-ops'] }, () => {
                cy.window().then(() => {
                    const page = new RulePageObject();

                    cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

                    cy.get(page.elements.loader).should('not.exist');
                    cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
                    cy.clickContextMenuItem(
                        '.sw-entity-listing__context-menu-edit-action',
                        page.elements.contextMenuButton,
                        `${page.elements.dataGridRow}--0`,
                    );

                    cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
                        .first()
                        .as('first-and-container');
                    cy.get('@first-and-container').should('be.visible');

                    cy.get('@first-and-container').within(() => {
                        cy.get('.sw-condition').as('condition-general');

                        page.selectTypeAndOperator('@condition-general', 'Item with property', 'Is one of');

                        cy.get('@condition-general').within(() => {
                            cy.get('.sw-select input').last().clearTypeAndCheck(resultCase.value);

                            cy.window().then(() => {
                                cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper')).should('be.visible');
                                cy.wrap(Cypress.$('.sw-select-result-list-popover-wrapper')).find('.sw-select-result').should('have.length', resultCase.length);
                            });
                        });
                    });
                });
            });
        });
    });
});
