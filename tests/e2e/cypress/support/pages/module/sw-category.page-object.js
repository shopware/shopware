import elements from '../sw-general.page-object';

export default class CategoryPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                categorySaveAction: '.sw-product-detail__save-action',
                categoryListName: `${elements.dataGridColumn}--name`,
                categoryTreeItem: '.sw-tree-item',
                categoryTreeItemInner: '.sw-category-tree__inner .sw-tree-item'
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

    selectCategory(value) {
        cy.get('.sw-category-tree__input-field').focus();
        cy.get('.sw-category-tree-field__results').should('be.visible');
        cy.get('.sw-tree-item__element').contains(value).parent().parent()
            .find('.sw-field__checkbox input')
            .click({ force: true });
        cy.get('.sw-category-tree-field__selected-label').contains(value).should('be.visible');
    }

    resetCategory() {
        cy.get('.sw-category-tree-field__selected-label').each(($el) => {
            $el.trigger('mouseenter').find('.sw-label__dismiss').trigger('click');
        });
    }

    clearCategory(category) {
        cy.get('.sw-category-tree-field__selected-label')
            .contains(category)
            .closest('.sw-category-tree-field__selected-label').each(($el) => {
                $el.trigger('mouseenter')
                    .find('.sw-label__dismiss')
                    .trigger('click');
            });
    }
}
