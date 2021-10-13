// / <reference types="Cypress" />

import RulePageObject from '../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Test all line items container crud operations', () => {
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_17016');
    });

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
            });
    });

    it('@rule: edit all line items container conditions', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');

        const page = new RulePageObject();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Ruler');

        cy.get(page.elements.loader).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Ruler');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');

        cy.get('@first-and-container').within(() => {
            // create first line item conditon
            cy.get('.sw-condition').as('first-condition');

            page.createBasicInputCondition({
                selector: '@first-condition',
                type: 'Line item price',
                operator: 'Is less than',
                inputName: 'amount',
                value: '12'
            });

            // test that any or all select exists
            cy.get('@first-condition').within(() => {
                cy.get('.sw-condition-base-line-item__matches-all').should('exist');
            });

            cy.get('button.sw-button').contains('And').click();

            // create second line item conditon
            cy.get('.sw-condition').eq(1).as('second-condition');
            page.createBasicInputCondition({
                selector: '@second-condition',
                type: 'Line item width',
                operator: 'Is greater than',
                inputName: 'amount',
                value: '100'
            });
        });

        // test that any or all select exists and select all
        cy.get('@second-condition').find('.sw-condition-base-line-item__matches-all').should('exist');
        cy.get('@second-condition').find('.sw-condition-base-line-item__matches-all')
            .typeSingleSelect('All line items', '.condition-content__spacer--and + .sw-condition .sw-condition-base-line-item__matches-all');

        cy.get('@first-and-container').within(() => {
            // test that condition is wrapped in all line items container
            cy.get('.condition-all-line-items-container').as('all-line-item-container');
            cy.get('@all-line-item-container').should('exist');

            // actual condition should be contained inside
            cy.get('@all-line-item-container').within(() => {
                cy.get('.sw-condition').as('contained-line-item-condition');
                cy.get('@contained-line-item-condition').should('exist');

                // check that values have been retained
                cy.get('@contained-line-item-condition').within(() => {
                    cy.get('.sw-condition-operator-select__select .sw-single-select__selection-text')
                        .should('contain', 'Is greater than');
                    cy.get('#sw-field--amount')
                        .should('have.value', '100');
                });
            });
        });

        // save and test that all line items container was persisted
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');

        cy.get('@first-and-container').within(() => {
            cy.get('.condition-all-line-items-container').as('all-line-item-container');
            cy.get('@all-line-item-container').should('exist');
        });

        // select any line items for second condition
        cy.get('@all-line-item-container').find('.sw-condition-base-line-item__matches-all')
            .typeSingleSelect('Any line item', '.condition-all-line-items-container .sw-condition-base-line-item__matches-all');

        cy.get('@first-and-container').within(() => {
            // test that condition is unwrapped from all line items container
            cy.get('.condition-all-line-items-container').should('not.exist');
            cy.get('.sw-condition').eq(1).as('second-condition');

            // check that values of unwrapped condition have been retained
            cy.get('@second-condition').within(() => {
                cy.get('.sw-condition-operator-select__select .sw-single-select__selection-text')
                    .should('contain', 'Is greater than');
                cy.get('#sw-field--amount')
                    .should('have.value', '100');
            });
        });

        // save again and test that all line items container removed and second condition remains
        cy.get('button.sw-button').contains('Save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-condition-tree .sw-condition-or-container .sw-condition-and-container')
            .first()
            .as('first-and-container');
        cy.get('@first-and-container').should('be.visible');

        cy.get('@first-and-container').within(() => {
            cy.get('.condition-all-line-items-container').should('not.exist');
            cy.get('.sw-condition').eq(1).should('exist');
        });
    });
});
