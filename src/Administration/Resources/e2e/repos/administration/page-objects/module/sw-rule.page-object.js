const GeneralPageObject = require('../sw-general.page-object');

class RuleBuilderPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements, ...{
                columnName: '.sw-settings-rule-list__column-name',
                ruleSaveAction: '.sw-settings-rule-detail__save-action'
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
            .waitForElementVisible('.sw-settings-rule-detail__save-action')
            .click('.sw-settings-rule-detail__save-action')
            .checkNotification(`The rule "${name}" has been saved successfully.`);
    }

    createBasicSelectCondition(type, operator, valueSelector, value) {
        this.browser
            .fillSelectField('select[name=type]', type)
            .fillSelectField('select[name=operator]', operator)
            .fillSwSelectComponent(
                valueSelector,
                {
                    value: value,
                    isMulti: true,
                    searchTerm: value
                }
            );
    }
}

module.exports = (browser) => {
    return new RuleBuilderPageObject(browser);
};
