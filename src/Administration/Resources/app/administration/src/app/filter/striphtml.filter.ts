/**
 * @package admin
 */

const { Filter } = Shopware;

Filter.register('striphtml', (value: string): string => {
    if (!value) {
        return '';
    }

    return value.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '');
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {};
