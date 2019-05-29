import { Filter } from 'src/core/shopware';

Filter.register('striphtml', (value) => {
    if (!value) {
        return '';
    }

    return value.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '');
});
