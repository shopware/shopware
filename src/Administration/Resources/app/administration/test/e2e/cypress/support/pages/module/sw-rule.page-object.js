const GeneralPageObject = require('../sw-general.page-object');

export default class RuleBuilderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                columnName: '.sw-settings-rule-list__column-name',
                ruleSaveAction: '.sw-settings-rule-detail__save-action',
                ruleDeleteAction: '.sw-condition-or-container__actions--delete'
            }
        };
    }

    createBasicSelectCondition({ selector, type, operator, value}) {
        this.selectTypeAndOperator(selector, type, operator);

        if (value !== undefined) {
            cy.get(selector).within(() => {
                cy.get('.sw-select').last().as('value-select');
                cy.get('@value-select').click();
                cy.get('.sw-select-result-list').should('be.visible').contains(value).click();
            });
        }
    }

    createBasicSelectConditionFromSearch({ selector, type, operator, value}) {
        this.selectTypeAndOperator(selector, type, operator);

        if (value !== undefined) {
            cy.get(selector).within(() => {
                cy.get('.sw-select input').last().type(value);
                cy.get('.sw-select-result-list').should('be.visible');
                cy.get('.sw-select-result-list .sw-select-result')
                    .should('have.length', 1)
                    .contains(value)
                    .click();
            });
        }
    }

    createBasicInputCondition({ selector, type, operator, inputName, value}) {
        this.selectTypeAndOperator(selector, type, operator);

        if (value !== undefined) {
            cy.get(selector).within(() => {
                cy.get(`input[name=sw-field--${inputName}]`).type(value).blur();
            });
        }
    }

    selectTypeAndOperator(selector, type, operator) {
        if (isNonEmptyString(type)) {
            cy.get(selector).within(() => {
                cy.get('.sw-condition-type-select .sw-select').click();
                cy.get('.sw-condition-type-select .sw-select .sw-select-result-list')
                    .scrollIntoView();
                cy.get('.sw-condition-type-select .sw-select .sw-select-result-list')
                    .contains(type)
                    .scrollIntoView()
                    .click();
            });
        }

        if (isNonEmptyString(operator)) {
            cy.get(selector).within(() => {
                cy.get('.sw-condition-operator-select .sw-select').click();
                cy.get('.sw-condition-operator-select .sw-select .sw-select-result-list').should('be.visible');
                cy.get('.sw-condition-operator-select .sw-select .sw-select-result-list').contains(operator).click();
            });
        }
    }
}

function isNonEmptyString(value) {
    return typeof value === 'string' && value !== '';
}
