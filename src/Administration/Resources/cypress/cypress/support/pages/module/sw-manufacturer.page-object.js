const GeneralPageObject = require('../sw-general.page-object');

export default class ManufacturerPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                manufacturerSave: '.sw-manufacturer-detail__save-action'
            }
        };
    }
}
