const GeneralPageObject = require('../sw-general.page-object');

export default class ProductPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                productSaveAction: '.sw-product-detail__save-action',
                productListName: `${this.elements.dataGridColumn}--name`
            }
        };
    }

    createTag(value, position = 0) {
        cy.get('.sw-tag-field .sw-multi-select__selection-item-input input')
            .type(value);
        cy.get('.sw-multi-select__results-empty-message').contains(`No results found for "${value}".`);
        cy.get('.sw-tag-field .sw-multi-select__selection-item-input input')
            .type('{enter}');
        cy.get(`.sw-multi-select__selection-item-holder--${position}`).contains(value);
        cy.get('.sw-tag-field .sw-multi-select__selection-item-input input').type('{esc}');
    }

    changeTranslation(language, position) {
        cy.get('.sw-language-switch').click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
        cy.get(`.sw-select-option:nth-of-type(${position})`).contains(language).click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
    }


    generateVariants(propertyName, optionPosition) {
        const optionsIndicator = '.sw-property-search__tree-selection__column-items-selected.sw-grid-column--right span';
        const optionString = optionPosition.length < 1 ? 'option' : 'options';

        cy.get('.group_grid__column-name').click();

        for (const entry in Object.values(optionPosition)) { // eslint-disable-line
            if (optionPosition.hasOwnProperty(entry)) {
                cy.get(
                    `.sw-property-search__tree-selection__option_grid .sw-grid__row--${entry} .sw-field__checkbox input`
                ).click();
            }
        }

        cy.get(`.sw-grid__row--0 ${optionsIndicator}`)
            .contains(`${optionPosition.length} ${optionString} selected`);
        cy.get(`.sw-modal__footer ${this.elements.primaryButton}`).contains('Generate variants');
        cy.get(`.sw-modal__footer ${this.elements.primaryButton}`).click();
        cy.get('.sw-product-modal-variant-generation__notification-modal .sw-button--primary')
            .click();
        cy.get('.sw-product-modal-variant-generation').should('not.exist');
    }
}
