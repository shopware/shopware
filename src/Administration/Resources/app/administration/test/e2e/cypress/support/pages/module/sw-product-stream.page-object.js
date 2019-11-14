const GeneralPageObject = require('../sw-general.page-object');

export default class ProductStreamPageObject extends GeneralPageObject {
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

    deleteProductStream(productStreamName) {
        cy.get('.sw-sidebar__navigation .sw-sidebar-navigation-item').click();
        cy.get(this.elements.loader).should('not.exist');
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            this.elements.contextMenuButton,
            `${this.elements.dataGridRow}--0`
        );
        cy.get(`${this.elements.modal}__body`)
            .contains(`Are you sure you want to delete the dynamic product group "${productStreamName}"?`);

        cy.get(`${this.elements.modal}__footer button${this.elements.primaryButton}`).click();
        cy.get(this.elements.modal).should('not.exist');
    }


    createBasicSelectCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type
            }
        );

        if (ruleData.operator) {
            cy.get(`${ruleData.ruleSelector} .field--operator`).typeLegacySelectAndCheck(
                ruleData.operator,
                {
                    searchTerm: ruleData.operator
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

    createBasicSwitchCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type
            }
        );
        cy.get(`${ruleData.ruleSelector} .field--main input`).click(ruleData.value);

        if (ruleData.operator) {
            cy.get(`${ruleData.ruleSelector} .sw-condition-operator-select`).typeLegacySelectAndCheck(
                ruleData.operator,
                {
                    searchTerm: ruleData.operator
                }
            );
        }
    }

    createBasicInputCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type
            }
        );
        cy.get(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`).clear();
        cy.get(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`).type(ruleData.value);

        if (ruleData.operator) {
            cy.get(`${ruleData.ruleSelector} .field--operator`).typeLegacySelectAndCheck(
                ruleData.operator,
                {
                    searchTerm: ruleData.operator
                }
            );
        }
    }

    createCombinedInputSelectCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}:nth-of-type(1)`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type
            }
        );
        cy.get(`${ruleData.ruleSelector} .field--condition:nth-of-type(2)`).typeLegacySelectAndCheck(
            ruleData.firstValue,
            {
                searchTerm: ruleData.firstValue,
                isMulti: ruleData.isMulti
            }
        );
        cy.get(`${ruleData.ruleSelector} .field--operator`).typeLegacySelectAndCheck(
            ruleData.operator,
            {
                searchTerm: ruleData.operator,
                isMulti: ruleData.isMulti
            }
        );
        cy.get(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`).clear();
        cy.get(`${ruleData.ruleSelector} input[name=${ruleData.inputName}]`).type(ruleData.secondValue);
    }

    createDateRangeCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type
            }
        );

        cy.get(`${ruleData.ruleSelector} .sw-select[name=useTime]`).typeLegacySelectAndCheck(
            ruleData.useTime ? 'Include time reference' : 'Exclude time reference',
            {
                isMulti: false
            }
        );

        cy.get('.field--from-date').fillAndCheckDateField(ruleData.fromDate);
        cy.get('.field--to-date').fillAndCheckDateField(ruleData.toDate);
    }

    createDateCondition(ruleData) {
        cy.get(`${ruleData.ruleSelector} ${this.elements.ruleFieldCondition}`).typeLegacySelectAndCheck(
            ruleData.type,
            {
                searchTerm: ruleData.type
            }
        );
        cy.get('.field--main').fillAndCheckDateField(ruleData.value);
    }
}
