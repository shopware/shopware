/**
 * @package inventory
 */
import elements from '../sw-general.page-object';

export default class ManufacturerPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                manufacturerSave: '.sw-manufacturer-detail__save-action'
            }
        };
    }
}
