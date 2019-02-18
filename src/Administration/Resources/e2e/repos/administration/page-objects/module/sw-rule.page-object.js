const GeneralPageObject = require('../sw-general.page-object');

class RuleBuilderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
                columnName: '.sw-settings-rule-list__column-name',
                ruleSaveAction: '.sw-settings-rule-detail__save-action',
                ruleDeleteAction: '.sw-condition-or-container__actions--delete',
                conditionOrContainer: '.sw-condition-container__or-child',
                conditionAndContainer: '.sw-condition-container__and-child',
                subConditionContainer: '.container-condition-level__is--even',
                ruleFieldCondition: '.field--condition',
                orSpacer: '.condition-content__spacer--or',
                andSpacer: '.condition-content__spacer--and'
            }
        };
    }

    createBasicRule(name) {
        this.browser
            .fillField('input[name=sw-field--rule-name]', name)
            .fillField('input[name=sw-field--rule-priority]', '1')
            .fillField('textarea[name=sw-field--rule-description]', 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.');

        this.createBasicSelectCondition('currency', 'Is one of', 'div[name=currencyIds]', 'Euro');

        this.browser
            .click(this.elements.ruleSaveAction)
            .checkNotification(`The rule "${name}" has been saved successfully.`);
    }

    createBasicSelectCondition(type, operator, ruleSelector, value) {
        this.browser
            .fillSwSelectComponent(`${ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: type,
                isMulti: false,
                searchTerm: type
            })
            .fillSwSelectComponent(`${ruleSelector} .field--select`, {
                value: operator,
                isMulti: false,
                searchTerm: operator
            })
            .fillSwSelectComponent(`${ruleSelector} .field--main`, {
                value: value,
                isMulti: true,
                searchTerm: value
            });
    }
}

module.exports = (browser) => {
    return new RuleBuilderPageObject(browser);
};
