const GeneralPageObject = require('../sw-general.page-object');

class RuleBuilderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                columnName: '.sw-data-grid__cell--name',
                ruleSaveAction: '.sw-settings-rule-detail__save-action',
                ruleDeleteAction: '.sw-condition-or-container__actions--delete'
            }
        };
    }

    createBasicRule(name) {
        this.browser
            .fillField('input[name=sw-field--rule-name]', name)
            .fillField('input[name=sw-field--rule-priority]', '1')
            .fillField('textarea[name=sw-field--rule-description]', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.');

        this.createBasicSelectCondition({
            type: 'Currency',
            operator: 'Is none of',
            ruleSelector: `${this.elements.conditionOrContainer}--0`,
            value: 'Euro',
            isMulti: true
        });

        this.browser
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.ruleSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    createBasicSelectCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false
            });

        if (ruleData.operator) {
            this.browser
                .fillSwSelect(`${ruleData.ruleSelector} .sw-condition-operator-select`, {
                    value: ruleData.operator,
                    isMulti: false
                });
        }
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} .field--main`, {
                value: ruleData.value,
                isMulti: ruleData.isMulti
            });
    }

    createBasicInputCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false
            });

        if (ruleData.operator) {
            this.browser
                .fillSwSelect(`${ruleData.ruleSelector} .sw-condition-operator-select`, {
                    value: ruleData.operator,
                    isMulti: false
                });
        }

        this.browser.fillField(`input[name=${ruleData.inputName}]`, ruleData.value);
    }

    createCombinedInputSelectCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false
            })
            .fillSwSelect(`${ruleData.ruleSelector} .sw-condition-operator-select`, {
                value: ruleData.operator,
                isMulti: ruleData.isMulti
            })
            .fillSwSelect(`${ruleData.ruleSelector} .sw-select[name=id]`, {
                value: ruleData.firstValue,
                isMulti: ruleData.isMulti
            })
            .fillField(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`, ruleData.secondValue);
    }

    createDateRangeCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, { value: ruleData.type });

        this.browser.fillSwSelect(`${ruleData.ruleSelector} .sw-select[name=useTime]`, {
            value: ruleData.useTime ? 'Including timestamp' : 'Excluding timestamp',
            isMulti: false
        });

        this.browser
            .fillDateField('.field--from-date', ruleData.fromDate)
            .fillDateField('.field--to-date', ruleData.toDate);
    }
}

module.exports = (browser) => {
    return new RuleBuilderPageObject(browser);
};
