import elements from '../sw-general.page-object';

export default class CategoryPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                categorySaveAction: '.sw-product-detail__save-action',
                categoryListName: `${elements.dataGridColumn}--name`,
                categoryTreeItem: '.sw-tree-item'
            }
        };
    }

    changeTranslation(language, position) {
        cy.get('.sw-language-switch').click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
        cy.get('.sw-select-result').should('be.visible');
        cy.get(`.sw-select-option--${position}`).contains(language).click();
        cy.get('.sw-field__select-load-placeholder').should('not.exist');
    }
}
