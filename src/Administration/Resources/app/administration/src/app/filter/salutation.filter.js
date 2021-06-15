const { Filter } = Shopware;

Filter.register('salutation', (entity, fallbackSnippet = '') => {
    if (!entity) {
        return fallbackSnippet;
    }

    let hideSalutation = true;
    if (entity.salutation) {
        hideSalutation = ['not_specified']
            .some((item) => item === entity.salutation.salutationKey);
    }

    const params = {
        salutation: !hideSalutation ? entity.salutation.displayName : '',
        title: entity.title || '',
        firstName: entity.firstName || '',
        lastName: entity.lastName || '',
    };

    const fullname = Object.values(params).join(' ').replace(/\s+/g, ' ').trim();

    if (fullname === '') {
        return fallbackSnippet;
    }

    return fullname;
});
