const GeneralPageObject = require('../sw-general.page-object');

class ProductStreamPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                columnName: '.sw-product-stream-list__column-name',
                streamSaveAction: '.sw-product-stream-detail__save-action'
            }
        };
    }

    createBasicProductStream(name, description) {
        this.browser
            .fillField('input[name=sw-field--productStream-name]', name)
            .fillField('textarea[name=sw-field--productStream-description]', description)
            .click(this.elements.streamSaveAction)
            .checkNotification(`The product stream "${name}" has been saved successfully.`);
    }

    deleteProductStream(productStreamName) {
        this.browser
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent(this.elements.loader)
            .clickContextMenuItem(this.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${this.elements.gridRow}--0`
            })
            .expect.element(`${this.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to delete the product stream "${productStreamName}"?`);

        this.browser
            .click(`.sw-modal__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal);
    }

    createBasicSelectCondition(ruleData) {
        this.browser
            .fillSwSelectComponent(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false,
                searchTerm: ruleData.type
            });

        if (ruleData.operator) {
            this.browser
                .fillSwSelectComponent(`${ruleData.ruleSelector} .field--operator`, {
                    value: ruleData.operator,
                    isMulti: false,
                    searchTerm: ruleData.operator
                });
        }
        this.browser
            .fillSwSelectComponent(`${ruleData.ruleSelector} .field--main`, {
                value: ruleData.value,
                isMulti: ruleData.isMulti,
                searchTerm: ruleData.value
            });
    }

    createBasicSwitchCondition(ruleData) {
        this.browser
            .fillSwSelectComponent(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false,
                searchTerm: ruleData.type
            })
            .tickCheckbox(`${ruleData.ruleSelector} .field--main input`, ruleData.value);

        if (ruleData.operator) {
            this.browser
                .fillSwSelectComponent(`${ruleData.ruleSelector} .sw-condition-operator-select`, {
                    value: ruleData.operator,
                    isMulti: false,
                    searchTerm: ruleData.operator
                });
        }
    }

    createBasicInputCondition(ruleData) {
        this.browser
            .fillSwSelectComponent(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false,
                searchTerm: ruleData.type
            })
            .fillField(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`, ruleData.value);

        if (ruleData.operator) {
            this.browser
                .fillSwSelectComponent(`${ruleData.ruleSelector} .field--operator`, {
                    value: ruleData.operator,
                    isMulti: false,
                    searchTerm: ruleData.operator
                });
        }
    }

    createCombinedInputSelectCondition(ruleData) {
        this.browser
            .fillSwSelectComponent(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}:nth-of-type(1)`, {
                value: ruleData.type,
                isMulti: false,
                searchTerm: ruleData.type
            })
            .fillSwSelectComponent(`${ruleData.ruleSelector} .field--condition:nth-of-type(2)`, {
                value: ruleData.firstValue,
                isMulti: ruleData.isMulti,
                searchTerm: ruleData.firstValue
            })
            .fillSwSelectComponent(`${ruleData.ruleSelector} .field--operator`, {
                value: ruleData.operator,
                isMulti: ruleData.isMulti,
                searchTerm: ruleData.operator
            })
            .fillField(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`, ruleData.secondValue);
    }

    createDateRangeCondition(ruleData) {
        this.browser
            .fillSwSelectComponent(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, {
                value: ruleData.type,
                isMulti: false,
                searchTerm: ruleData.type
            });

        this.browser.fillSwSelectComponent(`${ruleData.ruleSelector} .sw-select[name=useTime]`, {
            value: ruleData.useTime ? 'Use time' : 'Don\'t use time',
            isMulti: false,
            searchTerm: String(ruleData.useTime)
        });

        this.browser
            .fillDateField('.field--from-date input', ruleData.fromDate)
            .fillDateField('.field--to-date input', ruleData.toDate);
    }
}

module.exports = (browser) => {
    return new ProductStreamPageObject(browser);
};
