/**
 * @package admin
 */

const { Filter } = Shopware;
const { date } = Shopware.Utils.format;

Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions): string => {
    if (!value) {
        return '';
    }

    return date(value, options);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {};
