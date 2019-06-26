const GeneralPageObject = require('../sw-general.page-object');

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
}
