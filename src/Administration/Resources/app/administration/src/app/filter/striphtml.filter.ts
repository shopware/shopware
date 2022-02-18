const { Filter } = Shopware;

Filter.register('striphtml', (value: string): string => {
    if (!value) {
        return '';
    }

    return value.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '');
});

export default {};
