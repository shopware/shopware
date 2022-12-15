/**
 * @package admin
 */

const { Filter } = Shopware;
const { fileSize } = Shopware.Utils.format;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Filter.register('fileSize', (value, locale) => {
    if (!value) {
        return '';
    }

    return fileSize(value, locale);
});
