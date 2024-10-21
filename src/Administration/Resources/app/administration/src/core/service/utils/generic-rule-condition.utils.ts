/**
 * @package admin
 *
 * @module core/service/utils/object
 *
 * @private
 */
export default {
    getPlaceholderSnippet,
};

function getPlaceholderSnippet(fieldType: string): string {
    switch (fieldType) {
        case 'datetime':
        case 'date':
        case 'time':
            return `sw-datepicker.${fieldType}.placeholder`;
        default:
            return '';
    }
}
