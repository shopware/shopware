import GeneralPageObject from '../sw-general.page-object';

export default class CategoryPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                categorySaveAction: '.sw-product-detail__save-action',
                categoryListName: `${this.elements.dataGridColumn}--name`,
                categoryTreeItem: '.sw-tree-item'
            }
        };
    }

    changeTranslation(language, position) {
        cy.get('.sw-language-switch').click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
        cy.get(`.sw-select-option:nth-of-type(${position})`).contains(language).click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
    }
}
