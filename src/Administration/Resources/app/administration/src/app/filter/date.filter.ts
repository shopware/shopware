/**
 * @package admin
 */

const { Filter } = Shopware;
const { date } = Shopware.Utils.format;

Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return date(value, options);
});

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default {};
