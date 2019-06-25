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
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.streamSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    deleteProductStream(productStreamName) {
        this.browser
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementNotPresent(this.elements.loader)
            .clickContextMenuItem(this.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${this.elements.dataGridRow}--0`
            })
            .expect.element(`${this.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to delete the product group "${productStreamName}"?`);

        this.browser
            .click(`.sw-modal__footer button${this.elements.primaryButton}`)
            .waitForElementNotPresent(this.elements.modal);
    }

    createBasicSelectCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, { value: ruleData.type });

        if (ruleData.operator) {
            this.browser
                .fillSwSelect(`${ruleData.ruleSelector} .field--operator`, { value: ruleData.operator });
        }
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} .field--main`, {
                value: ruleData.value,
                isMulti: ruleData.isMulti
            });
    }

    createBasicSwitchCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, { value: ruleData.type })
            .tickCheckbox(`${ruleData.ruleSelector} .field--main input`, ruleData.value);

        if (ruleData.operator) {
            this.browser
                .fillSwSelect(`${ruleData.ruleSelector} .sw-condition-operator-select`, { value: ruleData.operator });
        }
    }

    createBasicInputCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, { value: ruleData.type })
            .fillField(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`, ruleData.value);

        if (ruleData.operator) {
            this.browser
                .fillSwSelect(`${ruleData.ruleSelector} .field--operator`, { value: ruleData.operator });
        }
    }

    createCombinedInputSelectCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}:nth-of-type(1)`, { value: ruleData.type })
            // .fillSwSelect(`${ruleData.ruleSelector} .field--condition:nth-of-type(2)`, {
            //     value: ruleData.firstValue,
            //     isMulti: ruleData.isMulti
            // })
            .fillSwSelect(`${ruleData.ruleSelector} .field--operator`, {
                value: ruleData.operator,
                isMulti: ruleData.isMulti
            })
            .fillField(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`, ruleData.firstValue);
    }

    createDateRangeCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, { value: ruleData.type });

        this.browser.fillSwSelect(`${ruleData.ruleSelector} .sw-select[name=useTime]`, {
            value: ruleData.useTime ? 'Include time reference' : 'Exclude time reference',
            isMulti: false
        });

        this.browser
            .fillDateField('.field--from-date', ruleData.fromDate)
            .fillDateField('.field--to-date', ruleData.toDate);
    }

    createDateCondition(ruleData) {
        this.browser
            .fillSwSelect(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`, { value: ruleData.type });

        this.browser
            .fillDateField('.field--main', ruleData.value);
    }
}

module.exports = (browser) => {
    return new ProductStreamPageObject(browser);
};
