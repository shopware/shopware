/**
 * @package admin
 */

Shopware.Filter.register('striphtml', (value: string): string => {
    if (!value) {
        return '';
    }

    return value.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '');
});

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default {};
