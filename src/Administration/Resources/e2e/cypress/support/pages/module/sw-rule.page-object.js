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

    createBasicRule(name) {
        cy.get('input[name=sw-field--rule-name]').type(name);
        cy.get('input[name=sw-field--rule-priority]').type('1');
        cy.get('textarea[name=sw-field--rule-description]').type('desc');

        cy.get('.field--condition').click();
        cy.get('.field--condition .sw-select-option--0').click();
        cy.get('.field--main').click();
        cy.get('.field--main .sw-select-option--0').click();
        cy.get(this.elements.smartBarHeader).click();
        cy.get(this.elements.successIcon).should('not.exist');
        cy.get(this.elements.ruleSaveAction).click();
        cy.get(this.elements.successIcon).should('be.visible');
    }

    createBasicSelectCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type,
                isMulti: false,
                clearField: false
            }
        );

        if (ruleData.operator) {
            cy.get(`${ruleData.ruleSelector} .sw-condition-operator-select`).typeLegacySelectAndCheck(
                ruleData.operator,
                {
                    searchTerm: ruleData.operator,
                    isMulti: false,
                    clearField: false
                }
            );
        }
        cy.get(`${ruleData.ruleSelector} .field--main`).typeLegacySelectAndCheck(
            ruleData.value,
            {
                searchTerm: ruleData.value,
                isMulti: ruleData.isMulti,
                clearField: false
            }
        );
    }

    createBasicInputCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type,
                isMulti: false,
                clearField: false
            }
        );

        if (ruleData.operator) {
            cy.get(`${ruleData.ruleSelector} .sw-condition-operator-select`).typeLegacySelectAndCheck(
                ruleData.operator,
                {
                    searchTerm: ruleData.operator,
                    isMulti: false,
                    clearField: false
                }
            );
        }
        cy.get(`input[name=${ruleData.inputName}]`).type(ruleData.value);
    }

    createCombinedInputSelectCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type,
                isMulti: false,
                clearField: false
            }
        );
        cy.get(`${ruleData.ruleSelector} .sw-condition-operator-select`).typeLegacySelectAndCheck(
            ruleData.operator,
            {
                searchTerm: ruleData.operator,
                isMulti: ruleData.isMulti,
                clearField: false
            }
        );
        cy.get(`${ruleData.ruleSelector} .sw-select[name=id]`).typeLegacySelectAndCheck(
            ruleData.firstValue,
            {
                searchTerm: ruleData.firstValue,
                isMulti: ruleData.isMulti,
                clearField: false
            }
        );
        cy.get(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`).type(ruleData.secondValue);
    }

    createDateRangeCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type,
                isMulti: ruleData.isMulti,
                clearField: false
            }
        );

        cy.get(`${ruleData.ruleSelector} .sw-select[name=useTime]`).typeLegacySelectAndCheck(
            ruleData.useTime ? 'Including timestamp' : 'Excluding timestamp',
            {
                searchTerm: ruleData.useTime ? 'Including timestamp' : 'Excluding timestamp',
                isMulti: false,
                clearField: false
            }
        );

        cy.get('.field--from-date').fillAndCheckDateField(ruleData.fromDate, '.field--from-date');
        cy.get('.field--to-date').fillAndCheckDateField(ruleData.toDate, '.field--to-date');
    }
}
