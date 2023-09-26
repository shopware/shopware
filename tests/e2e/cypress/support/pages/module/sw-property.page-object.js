/**
 * @package inventory
 */
import elements from '../sw-general.page-object';

export default class PropertyPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                mediaForm: '.sw-product-media-form',
                propertySaveAction: '.sw-property-detail__save-action',
                productListName: `${elements.dataGridColumn}--name`
            }
        };
    }
}
